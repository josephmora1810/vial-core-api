<?php

namespace App\Application\Towing\UseCases;

use App\Application\Payments\DTOs\OrderResponseDto;
use App\Application\Towing\DTOs\TowServiceResponseDto;
use App\Domain\Contracts\Repositories\ServiceContractRepositoryInterface;
use App\Domain\Payments\Entities\Order;
use App\Domain\Payments\Enums\OrderableType;
use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Payments\Repositories\OrderRepositoryInterface;
use App\Domain\Payments\Services\ExchangeRateServiceInterface;
use App\Domain\Shared\Services\BroadcasterInterface;
use App\Domain\Shared\Services\PushNotificationInterface;
use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Towing\Entities\TowService;
use App\Domain\Towing\Repositories\CoverageZoneRepositoryInterface;
use App\Domain\Towing\Service\RoutingServiceInterface;
use App\Domain\Towing\Repositories\TowServiceRepositoryInterface;
use App\Domain\Users\Repositories\SavedAddressRepositoryInterface;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use DomainException;
use Exception;
use InvalidArgumentException;

class RequestSosAssistance
{
    public function __construct(
        private CoverageZoneRepositoryInterface $coverageZoneRepository,
        private VehicleRepositoryInterface $vehicleRepository,
        private RoutingServiceInterface $routingService,
        private ServiceContractRepositoryInterface $contractRepository,
        private TowServiceRepositoryInterface $towServiceRepository,
        private OrderRepositoryInterface $orderRepository,
        private ExchangeRateServiceInterface $exchangeRateService,
        private ?SavedAddressRepositoryInterface $savedAddressRepository = null,
        private ?BroadcasterInterface $broadcaster = null,
        private ?PushNotificationInterface $pushNotificationService = null,
        private float $costPerExtraKm = 2.00,
        private float $baseSosPriceUsd = 35.00
    ) {}

    /**
     * Solicita asistencia vial de emergencia (SOS).
     *
     * @param int $clientId ID del cliente autenticado
     * @param string|int $vehicleIdentifier ID numérico o UUID del vehículo averiado
     * @param Coordinates $origin Coordenadas GPS del origen (accidente/avería)
     * @param string $originAddress Dirección en texto del origen
     * @param Coordinates $destination Coordenadas GPS del destino (taller/casa)
     * @param string $destinationAddress Dirección en texto del destino
     * @param string $failureDescription Descripción de la falla o motivo del auxilio
     * @param string|int|null $savedAddressIdentifier ID o UUID de la dirección guardada (opcional)
     * @return array<string, mixed>
     */
    public function execute(
        int $clientId,
        string|int $vehicleIdentifier,
        Coordinates $origin,
        string $originAddress,
        Coordinates $destination,
        string $destinationAddress,
        string $failureDescription,
        string|int|null $savedAddressIdentifier = null
    ): array {
        // 1. Geocerca restrictiva (FR-22): validar que el origen esté en la zona de cobertura
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
            throw new DomainException('No tienes autorización para solicitar asistencia para este vehículo.');
        }

        // 3. Resolver dirección guardada si fue suministrada
        $savedAddressId = null;
        if ($savedAddressIdentifier !== null && $this->savedAddressRepository !== null) {
            $savedAddress = is_numeric($savedAddressIdentifier)
                ? $this->savedAddressRepository->findById((int)$savedAddressIdentifier)
                : $this->savedAddressRepository->findByUuid(Uuid::fromString((string)$savedAddressIdentifier));

            if ($savedAddress !== null && $savedAddress->getUserId() === $clientId) {
                $savedAddressId = $savedAddress->getId();
            }
        }

        // 4. Calcular ruta y kilometraje con el motor OSRM
        $route = $this->routingService->calculateRoute($origin, $destination);
        $distanceKm = max(0.1, round($route->distanceKm, 2));

        // 5. Verificar póliza/contrato activo del vehículo (FR-20, FR-23-B)
        $contract = $this->contractRepository->findActiveByVehicleId((int)$vehicle->getId());

        $contractId = null;
        $extraKm = 0.0;
        $amountUsd = 0.0;

        if ($contract !== null && $contract->canCoverRescue()) {
            $contractId = $contract->getId();
            $coveredKm = $contract->getRemainingKm();

            if ($distanceKm > $coveredKm) {
                $extraKm = round($distanceKm - $coveredKm, 2);
                $amountUsd = round($extraKm * $this->costPerExtraKm, 2);
            }
        } else {
            // SOS Express / Particular (Sin contrato activo con servicios disponibles)
            $extraKm = $distanceKm;
            $amountUsd = round($this->baseSosPriceUsd + ($distanceKm * $this->costPerExtraKm), 2);
        }

        // 6. Crear el servicio de grúa en estado PENDING
        $service = TowService::create(
            clientId: $clientId,
            vehicleId: (int)$vehicle->getId(),
            origin: $origin,
            originAddress: trim($originAddress),
            destination: $destination,
            destinationAddress: trim($destinationAddress),
            failureDescription: trim($failureDescription),
            estimatedDistanceKm: $distanceKm,
            contractId: $contractId,
            savedAddressId: $savedAddressId,
            extraKm: $extraKm
        );
        $this->towServiceRepository->save($service);

        // 7. Generar orden de pago por excedente o servicio express si aplica
        $order = null;
        if ($amountUsd > 0) {
            $exchangeRate = $this->exchangeRateService->getExchangeRate('USD', 'venta');
            $amountVes = round($amountUsd * $exchangeRate, 2);

            $order = new Order(
                userId: $clientId,
                orderableType: OrderableType::TowService,
                orderableId: (int)$service->getId(),
                amountUsd: $amountUsd,
                exchangeRate: $exchangeRate,
                amountVes: $amountVes,
                status: OrderStatus::Pending
            );
            $order = $this->orderRepository->save($order);

            $service->assignExtraOrder((int)$order->getId());
            $this->towServiceRepository->save($service);
        }

        $serviceDto = TowServiceResponseDto::fromEntity(
            service: $service,
            vehiclePlate: $vehicle->getPlateNumber()->getValue(),
            contractCode: $contract?->getVerificationCode(),
            extraOrderUuid: $order?->getUuid()->getValue()
        )->toArray();

        // 8. Difusión en tiempo real (WebSockets / Pusher) al canal privado de grúas (FR-23)
        // Seguridad financiera: Solo difundir al radar si no requiere pago previo de excedente / express
        if ($order === null) {
            $this->broadcaster?->broadcast(
                channels: 'private-cranes-radar',
                event: 'new-sos-request',
                data: $serviceDto
            );

            // 8.1 Notificación Push nativa (Pusher Beams / FCM) si el chofer tiene la app en segundo plano
            $this->pushNotificationService?->publishToInterests(
                interests: ['cranes-radar'],
                title: '🚨 ¡NUEVA SOLICITUD DE AUXILIO VIAL!',
                body: "Avería reportada en {$service->getOriginAddress()}. Toca para ver la solicitud.",
                data: [
                    'service_uuid' => $service->getUuid()->getValue(),
                    'origin_lat'   => (string)$origin->getLatitude(),
                    'origin_lng'   => (string)$origin->getLongitude(),
                ]
            );
        }

        return [
            'service'          => $serviceDto,
            'requires_payment' => $order !== null,
            'order'            => $order !== null ? OrderResponseDto::fromEntity($order)->toArray() : null,
            'route'            => [
                'distance_km'      => $distanceKm,
                'duration_minutes' => round($route->durationMinutes, 1),
            ],
        ];
    }
}
