<?php

namespace Tests\Doubles;

use App\Domain\Vehicles\Entities\Vehicle;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use App\Domain\Vehicles\ValueObjects\Niv;
use App\Domain\Vehicles\ValueObjects\PlateNumber;
use App\Domain\Shared\ValueObjects\Uuid;

class InMemoryVehicleRepository implements VehicleRepositoryInterface
{
    /** @var array<int, Vehicle> */
    private array $vehicles = [];
    private int $nextId = 1;

    public function save(Vehicle $vehicle): void
    {
        if ($vehicle->getId() === null) {
            $vehicle->setId($this->nextId++);
        }
        $this->vehicles[$vehicle->getId()] = $vehicle;
    }

    public function findById(int $id): ?Vehicle
    {
        $v = $this->vehicles[$id] ?? null;
        return ($v && !$v->isDeleted()) ? $v : null;
    }

    public function findByUuid(Uuid $uuid): ?Vehicle
    {
        foreach ($this->vehicles as $vehicle) {
            if ($vehicle->getUuid()->equals($uuid) && !$vehicle->isDeleted()) {
                return $vehicle;
            }
        }
        return null;
    }

    public function findByPlateNumber(PlateNumber $plateNumber): ?Vehicle
    {
        foreach ($this->vehicles as $vehicle) {
            if ($vehicle->getPlateNumber()->equals($plateNumber) && !$vehicle->isDeleted()) {
                return $vehicle;
            }
        }
        return null;
    }

    public function findByNiv(Niv $niv): ?Vehicle
    {
        foreach ($this->vehicles as $vehicle) {
            if ($vehicle->getNiv()->equals($niv) && !$vehicle->isDeleted()) {
                return $vehicle;
            }
        }
        return null;
    }

    public function findAllByUserId(int $userId): array
    {
        $result = [];
        foreach ($this->vehicles as $vehicle) {
            if ($vehicle->getUserId() === $userId && !$vehicle->isDeleted()) {
                $result[] = $vehicle;
            }
        }
        return $result;
    }

    public function findCranesByUserId(int $userId): array
    {
        $result = [];
        foreach ($this->vehicles as $vehicle) {
            if ($vehicle->getUserId() === $userId && $vehicle->isCrane() && !$vehicle->isDeleted()) {
                $result[] = $vehicle;
            }
        }
        return $result;
    }

    public function softDeleteByUuid(Uuid $uuid, int $userId): bool
    {
        foreach ($this->vehicles as $vehicle) {
            if ($vehicle->getUuid()->equals($uuid) && $vehicle->getUserId() === $userId && !$vehicle->isDeleted()) {
                $vehicle->softDelete();
                return true;
            }
        }
        return false;
    }
}
