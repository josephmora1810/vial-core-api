<?php

namespace App\Application\Towing\UseCases;

use App\Domain\Contracts\Repositories\ServiceContractRepositoryInterface;
use App\Domain\Driver\Repositories\DriverShiftRepositoryInterface;
use App\Domain\Payments\Services\ExchangeRateServiceInterface;
use App\Domain\Shared\Services\RedisClientInterface;
use App\Domain\Shared\ValueObjects\{Coordinates, Uuid};
use App\Domain\Towing\{
    Repositories\CoverageZoneRepositoryInterface,
    Service\RoutingServiceInterface
};
use App\Domain\Users\Repositories\SavedAddressRepositoryInterface;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use DomainException;
use Exception;
use Throwable;

class QuoteSosAssistance
{
    public function __construct(
        private CoverageZoneRepositoryInterface $coverageZoneRepository,
        private VehicleRepositoryInterface $vehicleRepository,
        private RoutingServiceInterface $routingService,
        private ServiceContractRepositoryInterface $contractRepository,
        private DriverShiftRepositoryInterface $driverShiftRepository,
        private ExchangeRateServiceInterface $exchangeRateService,
        private ?RedisClientInterface $redis = null,
        private ?SavedAddressRepositoryInterface $savedAddressRepository = null,
        private float $costPerExtraKm = 2.00,
        private float $baseSosPriceUsd = 35.00
    ) {}

    /**
     * Realiza la pre-cotización de un servicio de auxilio vial (SOS) y verifica la disponibilidad de grúas en radar.
     * Si no hay grúas disponibles en el radio operativo, advierte al usuario sin obligarlo a pagar.
     *
     * @param int $clientId
     * @param string|int $vehicleIdentifier
     * @param Coordinates $origin
     * @param Coordinates $destination
     * @param string|int|null $savedAddressIdentifier
     * @param float $searchRadiusKm Radio de búsqueda en km para el radar (default: 25 km)
     * @return array<string, mixed>
     */
    public function execute(
        int $clientId,
        string|int $vehicleIdentifier,
        Coordinates $origin,
        Coordinates $destination,
        string|int|null $savedAddressIdentifier = null,
        float $searchRadiusKm = 25.0
    ): array {
        // 1. Geocerca restrictiva (FR-22)
        $zone = $this->coverageZoneRepository->findMatchingZone($origin);
        if ($zone === null) {
            throw new DomainException(
                'La ubicación de origen se encuentra fuera del área de cobertura operativa (Gran Caracas y Litoral Central).'
            );
        }

        // 2. Resolver y validar el vehículo del cliente
        $vehicle = is_numeric($vehicleIdentifier)
            ? $this->vehicleRepository->findById((int)$vehicleIdentifier)
            : $this->vehicleRepository->findByUuid(Uuid::fromString((string)$vehicleIdentifier));

        if ($vehicle === null || !$vehicle->isActive()) {
            throw new DomainException('El vehículo indicado no existe o no se encuentra activo.');
        }

        if ($vehicle->getUserId() !== $clientId) {
            throw new DomainException('No tienes autorización para cotizar asistencia para este vehículo.');
        }

        // 3. Resolver dirección guardada si aplica
        $savedAddressName = null;
        if ($savedAddressIdentifier !== null && $this->savedAddressRepository !== null) {
            $savedAddress = is_numeric($savedAddressIdentifier)
                ? $this->savedAddressRepository->findById((int)$savedAddressIdentifier)
                : $this->savedAddressRepository->findByUuid(Uuid::fromString((string)$savedAddressIdentifier));

            if ($savedAddress !== null && $savedAddress->getUserId() === $clientId) {
                $savedAddressName = $savedAddress->getName();
            }
        }

        // 4. Verificación de Grúas Disponibles en Radar (Redis GEOSEARCH con fallback a Haversine en BD)
        $nearbyCranesCount = 0;
        $nearbyCranesList = [];

        if ($this->redis !== null) {
            try {
                // GEOSEARCH cranes:locations FROMLONLAT {lng} {lat} BYRADIUS {radius} km WITHDIST
                $redisResults = $this->redis->execute(
                    'GEOSEARCH',
                    'cranes:locations',
                    'FROMLONLAT',
                    (string)$origin->getLongitude(),
                    (string)$origin->getLatitude(),
                    'BYRADIUS',
                    (string)$searchRadiusKm,
                    'km',
                    'WITHDIST'
                );

                if (is_array($redisResults)) {
                    $nearbyCranesCount = count($redisResults);
                }
            } catch (Throwable) {
                // Fallback a MariaDB si Redis no responde
                $nearbyDrivers = $this->driverShiftRepository->findNearbyActiveDrivers(
                    $origin->getLatitude(),
                    $origin->getLongitude(),
                    $searchRadiusKm
                );
                $nearbyCranesCount = count($nearbyDrivers);
            }
        } else {
            $nearbyDrivers = $this->driverShiftRepository->findNearbyActiveDrivers(
                $origin->getLatitude(),
                $origin->getLongitude(),
                $searchRadiusKm
            );
            $nearbyCranesCount = count($nearbyDrivers);
        }

        // 5. Calcular ruta, distancia y duración con motor OSRM
        $route = $this->routingService->calculateRoute($origin, $destination);
        $distanceKm = max(0.1, round($route->distanceKm, 2));
        $durationMinutes = max(1.0, round($route->durationMinutes, 1));

        // 6. Verificar cobertura de contrato o calcular tarifa express
        $contract = $this->contractRepository->findActiveByVehicleId((int)$vehicle->getId());
        $isCoveredByPlan = false;
        $planName = null;
        $remainingServices = 0;
        $remainingKm = 0.0;
        $extraKm = 0.0;
        $amountUsd = 0.0;

        if ($contract !== null && $contract->canCoverRescue()) {
            $isCoveredByPlan = true;
            $remainingServices = $contract->getRemainingServices();
            $remainingKm = $contract->getRemainingKm();

            if ($distanceKm > $remainingKm) {
                $extraKm = round($distanceKm - $remainingKm, 2);
                $amountUsd = round($extraKm * $this->costPerExtraKm, 2);
            }
        } else {
            // SOS Express / Particular
            $extraKm = $distanceKm;
            $amountUsd = round($this->baseSosPriceUsd + ($distanceKm * $this->costPerExtraKm), 2);
        }

        // 7. Conversión a Bolívares (VES) a tasa oficial BCV
        $exchangeRate = $this->exchangeRateService->getExchangeRate('USD', 'venta');
        $amountVes = round($amountUsd * $exchangeRate, 2);

        $canRequestService = ($nearbyCranesCount > 0);
        $warningMessage = null;

        if ($nearbyCranesCount === 0) {
            $warningMessage = 'En este momento no hay grúas operativas disponibles cerca de tu ubicación. Te recomendamos intentar en unos minutos para no generar cobros innecesarios.';
        }

        return [
            'can_request_service' => $canRequestService,
            'nearby_cranes_count' => $nearbyCranesCount,
            'search_radius_km'    => $searchRadiusKm,
            'route' => [
                'distance_km'      => $distanceKm,
                'duration_minutes' => $durationMinutes,
                'origin'           => [
                    'latitude'  => $origin->getLatitude(),
                    'longitude' => $origin->getLongitude(),
                ],
                'destination'      => [
                    'latitude'  => $destination->getLatitude(),
                    'longitude' => $destination->getLongitude(),
                ],
                'saved_address'    => $savedAddressName,
            ],
            'pricing' => [
                'is_covered_by_plan' => $isCoveredByPlan,
                'remaining_services' => $remainingServices,
                'remaining_km'       => $remainingKm,
                'extra_km'           => $extraKm,
                'amount_usd'         => $amountUsd,
                'amount_ves'         => $amountVes,
                'exchange_rate'      => $exchangeRate,
                'requires_payment'   => $amountUsd > 0,
            ],
            'warning_message'     => $warningMessage,
        ];
    }
}
