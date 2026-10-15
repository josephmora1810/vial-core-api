<?php

namespace App\Application\UseCases;

use App\Application\DTOs\VehicleResponseDto;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Repositories\VehicleRepositoryInterface;
use Exception;

class ListUserVehicles
{
    public function __construct(
        private VehicleRepositoryInterface $vehicleRepository,
        private UserRepositoryInterface $userRepository
    ) {}

    /**
     * @return array<array>
     */
    public function execute(int $userId): array
    {
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new Exception("Usuario no encontrado.", 404);
        }

        $vehicles = $this->vehicleRepository->findAllByUserId($userId);

        return array_map(
            fn($v) => VehicleResponseDto::fromEntity($v)->toArray(),
            $vehicles
        );
    }
}
