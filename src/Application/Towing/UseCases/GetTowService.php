<?php

namespace App\Application\Towing\UseCases;

use App\Application\Towing\DTOs\TowServiceResponseDto;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Towing\Repositories\TowServiceRepositoryInterface;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use Exception;

class GetTowService
{
    public function __construct(
        private TowServiceRepositoryInterface $towServiceRepository,
        private ?VehicleRepositoryInterface $vehicleRepository = null
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string|int $serviceIdentifier, ?int $requestingUserId = null): array
    {
        $service = is_numeric($serviceIdentifier)
            ? $this->towServiceRepository->findById((int)$serviceIdentifier)
            : $this->towServiceRepository->findByUuid(Uuid::fromString((string)$serviceIdentifier));

        if ($service === null) {
            throw new Exception('El servicio de asistencia solicitado no existe.', 404);
        }

        $plate = null;
        if ($this->vehicleRepository !== null) {
            $vehicle = $this->vehicleRepository->findById($service->getVehicleId());
            $plate = $vehicle?->getPlateNumber()->getValue();
        }

        return TowServiceResponseDto::fromEntity($service, $plate)->toArray();
    }
}
