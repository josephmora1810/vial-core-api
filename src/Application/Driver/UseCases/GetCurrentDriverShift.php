<?php

namespace App\Application\Driver\UseCases;

use App\Application\Driver\DTOs\DriverShiftResponseDto;
use App\Domain\Driver\Repositories\DriverShiftRepositoryInterface;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;

class GetCurrentDriverShift
{
    public function __construct(
        private DriverShiftRepositoryInterface $driverShiftRepository,
        private ?VehicleRepositoryInterface $vehicleRepository = null
    ) {}

    public function execute(int $driverId): DriverShiftResponseDto
    {
        $shift = $this->driverShiftRepository->findActiveByDriverId($driverId);

        if ($shift === null || !$shift->isActive()) {
            return DriverShiftResponseDto::inactive();
        }

        $crane = null;
        if ($this->vehicleRepository !== null) {
            $crane = $this->vehicleRepository->findById($shift->getCraneId());
        }

        return DriverShiftResponseDto::fromEntity($shift, $crane);
    }
}
