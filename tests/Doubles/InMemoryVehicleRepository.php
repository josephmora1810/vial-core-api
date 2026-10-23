<?php

namespace Tests\Doubles;

use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Vehicles\Entities\Vehicle;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use App\Domain\Vehicles\ValueObjects\Niv;
use App\Domain\Vehicles\ValueObjects\PlateNumber;

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
            if ($vehicle->getPlateNumber() !== null && $vehicle->getPlateNumber()->equals($plateNumber) && !$vehicle->isDeleted()) {
                return $vehicle;
            }
        }
        return null;
    }

    public function findByNiv(Niv $niv): ?Vehicle
    {
        foreach ($this->vehicles as $vehicle) {
            if ($vehicle->getNiv() !== null && $vehicle->getNiv()->equals($niv) && !$vehicle->isDeleted()) {
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

    public function findDetailedByUuid(Uuid $uuid): ?array
    {
        $v = $this->findByUuid($uuid);
        if ($v === null) {
            return null;
        }

        return [
            'id'                  => $v->getId(),
            'uuid'                => $v->getUuid()->getValue(),
            'user_id'             => $v->getUserId(),
            'model_id'            => $v->getModelId(),
            'plate_number'        => $v->getPlateNumber()?->getValue(),
            'niv'                 => $v->getNiv()?->getValue(),
            'engine_serial'       => $v->getEngineSerial()?->getValue(),
            'year'                => $v->getYear(),
            'color'               => $v->getColor(),
            'vehicle_type'        => $v->getType()->getValue(),
            'vehicle_status'      => $v->getStatus()->getValue(),
            'is_verified'         => $v->isVerified(),
            'created_at'          => $v->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at'          => $v->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'owner_names'         => 'Test',
            'owner_surnames'      => 'User',
            'owner_national_id'   => 'V12345678',
            'owner_phone'         => '04141234567',
            'owner_email'         => 'test@example.com',
            'brand_id'            => 1,
            'brand_name'          => $v->getBrandName() ?? 'Toyota',
            'model_name'          => $v->getModelName() ?? 'Corolla',
            'vehicle_subclass_id' => 1,
            'subclass_name'       => $v->getSubclassName() ?? 'Sedan',
            'vehicle_class_id'    => 1,
            'class_name'          => $v->getClassName() ?? 'Automovil',
            'class_code'          => 'AUTOMOVIL',
            'document_id'         => null,
            'document_type'       => null,
            'document_file_path'  => null,
            'document_status'     => null,
        ];
    }

    public function findDetailedByPlateNumber(PlateNumber $plateNumber): ?array
    {
        $v = $this->findByPlateNumber($plateNumber);
        return $v ? $this->findDetailedByUuid($v->getUuid()) : null;
    }

    public function findDetailedByUserId(int $userId): array
    {
        $vehicles = $this->findAllByUserId($userId);
        return array_map(fn($v) => $this->findDetailedByUuid($v->getUuid()), $vehicles);
    }
}
