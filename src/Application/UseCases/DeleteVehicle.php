<?php

namespace App\Application\UseCases;

use App\Domain\Repositories\VehicleRepositoryInterface;
use App\Domain\ValueObjects\Uuid;
use Exception;

class DeleteVehicle
{
    public function __construct(
        private VehicleRepositoryInterface $vehicleRepository
    ) {}

    public function execute(string $uuidString, int $userId): bool
    {
        $uuid = new Uuid($uuidString);
        $deleted = $this->vehicleRepository->softDeleteByUuid($uuid, $userId);

        if (!$deleted) {
            throw new Exception("Vehículo no encontrado o no pertenece a tu cuenta.", 404);
        }

        return true;
    }
}
