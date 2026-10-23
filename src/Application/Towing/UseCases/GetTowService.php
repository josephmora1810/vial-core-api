<?php

namespace App\Application\Towing\UseCases;

use App\Application\Towing\DTOs\TowServiceResponseDto;
use App\Domain\Driver\Repositories\DriverShiftRepositoryInterface;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Towing\Repositories\RatingRepositoryInterface;
use App\Domain\Towing\Repositories\TowServiceRepositoryInterface;
use App\Domain\Users\Repositories\UserRepositoryInterface;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use Exception;
use InvalidArgumentException;

class GetTowService
{
    public function __construct(
        private TowServiceRepositoryInterface $towServiceRepository,
        private ?VehicleRepositoryInterface $vehicleRepository = null,
        private ?UserRepositoryInterface $userRepository = null,
        private ?DriverShiftRepositoryInterface $driverShiftRepository = null,
        private ?RatingRepositoryInterface $ratingRepository = null
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
        $vehicleData = null;
        if ($this->vehicleRepository !== null) {
            $vehicle = $this->vehicleRepository->findById($service->getVehicleId());
            if ($vehicle !== null) {
                $plate = $vehicle->getPlateNumber()?->getValue();
                $vehicleData = [
                    'id'           => $vehicle->getId(),
                    'brand'        => $vehicle->getBrandName(),
                    'model'        => $vehicle->getModelName(),
                    'year'         => $vehicle->getYear(),
                    'color'        => $vehicle->getColor(),
                    'plate_number' => $plate,
                ];
            }
        }

        // Enriquecer datos del cliente
        $clientData = null;
        if ($this->userRepository !== null) {
            $client = $this->userRepository->findById($service->getClientId());
            if ($client !== null) {
                $clientData = [
                    'id'         => $client->getId(),
                    'name'       => $client->getFullName(),
                    'phone'      => $client->getPhone()->getValue(),
                    'initials'   => $client->getInitials(),
                ];
            }
        }

        // Enriquecer datos del chofer y grúa (si ya fue asignado)
        $driverData = null;
        $craneData = null;
        if ($service->getDriverId() !== null) {
            if ($this->userRepository !== null) {
                $driver = $this->userRepository->findById($service->getDriverId());
                if ($driver !== null) {
                    $rating = $this->ratingRepository?->getAverageRatingForUser($service->getDriverId()) ?? 5.0;
                    $driverData = [
                        'id'       => $driver->getId(),
                        'name'     => $driver->getFullName(),
                        'phone'    => $driver->getPhone()->getValue(),
                        'initials' => $driver->getInitials(),
                        'rating'   => round($rating, 2),
                    ];
                }
            }

            if ($this->driverShiftRepository !== null) {
                $shift = $this->driverShiftRepository->findActiveByDriverId($service->getDriverId());
                if ($shift !== null && $this->vehicleRepository !== null) {
                    $crane = $this->vehicleRepository->findById($shift->getCraneId());
                    if ($crane !== null) {
                        $craneData = [
                            'id'           => $crane->getId(),
                            'brand'        => $crane->getBrandName(),
                            'model'        => $crane->getModelName(),
                            'color'        => $crane->getColor(),
                            'plate_number' => $crane->getPlateNumber()?->getValue(),
                        ];
                    }
                }
            }
        }

        return TowServiceResponseDto::fromEntity(
            service: $service,
            vehiclePlate: $plate,
            driver: $driverData,
            crane: $craneData,
            client: $clientData,
            vehicle: $vehicleData
        )->toArray();
    }
}
