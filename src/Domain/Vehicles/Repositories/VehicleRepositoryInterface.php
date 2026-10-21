<?php

namespace App\Domain\Vehicles\Repositories;

use App\Domain\Vehicles\Entities\Vehicle;
use App\Domain\Vehicles\ValueObjects\Niv;
use App\Domain\Vehicles\ValueObjects\PlateNumber;
use App\Domain\Shared\ValueObjects\Uuid;

interface VehicleRepositoryInterface
{
    public function save(Vehicle $vehicle): void;

    public function findById(int $id): ?Vehicle;

    public function findByUuid(Uuid $uuid): ?Vehicle;

    public function findByPlateNumber(PlateNumber $plateNumber): ?Vehicle;

    public function findByNiv(Niv $niv): ?Vehicle;

    /**
     * @return Vehicle[]
     */
    public function findAllByUserId(int $userId): array;

    public function softDeleteByUuid(Uuid $uuid, int $userId): bool;
}
