<?php

namespace App\Application\Towing\UseCases;

use App\Application\Towing\DTOs\TowServiceResponseDto;
use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Towing\Repositories\TowServiceRepositoryInterface;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;

class FindNearbyPendingServices
{
    public function __construct(
        private TowServiceRepositoryInterface $towServiceRepository,
        private ?VehicleRepositoryInterface $vehicleRepository = null
    ) {}

    /**
     * Retorna servicios en estado PENDING dentro de un radio en kilómetros (FR-23 Radar).
     *
     * @param Coordinates $driverLocation Coordenadas actuales del chofer
     * @param float $radiusKm Radio de búsqueda en km (default 25.0)
     * @return list<array<string, mixed>>
     */
    public function execute(Coordinates $driverLocation, float $radiusKm = 25.0): array
    {
        $services = $this->towServiceRepository->findPendingNearby($driverLocation, $radiusKm);

        $results = [];
        foreach ($services as $service) {
            $plate = null;
            if ($this->vehicleRepository !== null) {
                $vehicle = $this->vehicleRepository->findById($service->getVehicleId());
                $plate = $vehicle?->getPlateNumber()->getValue();
            }
            $results[] = TowServiceResponseDto::fromEntity($service, $plate)->toArray();
        }

        return $results;
    }
}
