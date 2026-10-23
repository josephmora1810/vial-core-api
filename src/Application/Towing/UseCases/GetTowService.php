<?php

namespace App\Application\Towing\UseCases;

use App\Application\Towing\DTOs\TowServiceResponseDto;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Towing\Repositories\TowServiceRepositoryInterface;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use Exception;
use InvalidArgumentException;

class GetTowService
{
    public function __construct(
        private TowServiceRepositoryInterface $towServiceRepository,
        private ?VehicleRepositoryInterface $vehicleRepository = null
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string|int $serviceIdentifier, ?int $requestingUserId = null, bool $isAdmin = false): array
    {
        if (is_numeric($serviceIdentifier)) {
            $service = $this->towServiceRepository->findById((int)$serviceIdentifier);
        } else {
            try {
                $uuid = Uuid::fromString((string)$serviceIdentifier);
                $service = $this->towServiceRepository->findByUuid($uuid);
            } catch (InvalidArgumentException) {
                throw new Exception('El identificador del servicio debe ser un UUID válido.', 400);
            }
        }

        if ($service === null) {
            throw new Exception('El servicio de asistencia solicitado no existe.', 404);
        }

        // BOLA Prevention (OWASP API1:2023): Validar que quien consulta sea el cliente, el conductor o un administrador
        if (!$isAdmin && $requestingUserId !== null) {
            $isClient = ($service->getClientId() === $requestingUserId);
            $isDriver = ($service->getDriverId() === $requestingUserId);
            if (!$isClient && !$isDriver) {
                throw new Exception('No tienes autorización para consultar este servicio de auxilio vial.', 403);
            }
        }

        $plate = null;
        if ($this->vehicleRepository !== null) {
            $vehicle = $this->vehicleRepository->findById($service->getVehicleId());
            $plate = $vehicle?->getPlateNumber()->getValue();
        }

        return TowServiceResponseDto::fromEntity($service, $plate)->toArray();
    }
}
