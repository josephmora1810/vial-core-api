<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\Vehicle;
use App\Domain\ValueObjects\Niv;
use App\Domain\ValueObjects\PlateNumber;
use App\Domain\ValueObjects\Uuid;

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
