<?php

namespace App\Domain\Driver\Repositories;

use App\Domain\Driver\Entities\DriverShift;

interface DriverShiftRepositoryInterface
{
    public function save(DriverShift $shift): DriverShift;

    public function findActiveByDriverId(int $driverId): ?DriverShift;

    public function findActiveByCraneId(int $craneId): ?DriverShift;

    public function endActiveShift(int $driverId): void;

    public function updateLocation(int $driverId, float $latitude, float $longitude): void;

    /**
     * @param float $latitude
     * @param float $longitude
     * @param float $radiusKm
     * @return list<array<string, mixed>>
     */
    public function findNearbyActiveDrivers(float $latitude, float $longitude, float $radiusKm = 25.0): array;
}
