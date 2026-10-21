<?php

namespace App\Infrastructure\Database\Repositories\Vehicles;

use App\Domain\Vehicles\Entities\Vehicle;
use App\Domain\Vehicles\Enums\VehicleStatus;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use App\Domain\Vehicles\ValueObjects\EngineSerial;
use App\Domain\Vehicles\ValueObjects\Niv;
use App\Domain\Vehicles\ValueObjects\PlateNumber;
use App\Domain\Shared\ValueObjects\Uuid;
use DateTimeImmutable;
use PDO;

class MariaDbVehicleRepository implements VehicleRepositoryInterface
{
    public function __construct(private PDO $db) {}

    public function save(Vehicle $vehicle): void
    {
        if ($vehicle->getId() === null) {
            $sql = "
                INSERT INTO vehicles (
                    uuid,
                    user_id,
                    vehicle_class_id,
                    vehicle_subclass_id,
                    brand_id,
                    model_id,
                    brand_name,
                    model_name,
                    plate_number,
                    niv,
                    engine_serial,
                    year,
                    color,
                    status,
                    is_verified,
                    created_at,
                    updated_at,
                    deleted_at
                ) VALUES (
                    :uuid,
                    :user_id,
                    :vehicle_class_id,
                    :vehicle_subclass_id,
                    :brand_id,
                    :model_id,
                    :brand_name,
                    :model_name,
                    :plate_number,
                    :niv,
                    :engine_serial,
                    :year,
                    :color,
                    :status,
                    :is_verified,
                    :created_at,
                    :updated_at,
                    :deleted_at
                )
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'uuid'                => $vehicle->getUuid()->getValue(),
                'user_id'             => $vehicle->getUserId(),
                'vehicle_class_id'    => $vehicle->getVehicleClassId(),
                'vehicle_subclass_id' => $vehicle->getVehicleSubclassId(),
                'brand_id'            => $vehicle->getBrandId(),
                'model_id'            => $vehicle->getModelId(),
                'brand_name'          => $vehicle->getBrandName(),
                'model_name'          => $vehicle->getModelName(),
                'plate_number'        => $vehicle->getPlateNumber()->getValue(),
                'niv'                 => $vehicle->getNiv()->getValue(),
                'engine_serial'       => $vehicle->getEngineSerial()?->getValue(),
                'year'                => $vehicle->getYear(),
                'color'               => $vehicle->getColor(),
                'status'              => $vehicle->getStatus()->getValue(),
                'is_verified'         => $vehicle->isVerified() ? 1 : 0,
                'created_at'          => $vehicle->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at'          => $vehicle->getUpdatedAt()?->format('Y-m-d H:i:s') ?? $vehicle->getCreatedAt()->format('Y-m-d H:i:s'),
                'deleted_at'          => $vehicle->getDeletedAt()?->format('Y-m-d H:i:s'),
            ]);

            $vehicle->setId((int)$this->db->lastInsertId());
        } else {
            $sql = "
                UPDATE vehicles SET
                    vehicle_class_id    = :vehicle_class_id,
                    vehicle_subclass_id = :vehicle_subclass_id,
                    brand_id            = :brand_id,
                    model_id            = :model_id,
                    brand_name          = :brand_name,
                    model_name          = :model_name,
                    plate_number        = :plate_number,
                    niv                 = :niv,
                    engine_serial       = :engine_serial,
                    year                = :year,
                    color               = :color,
                    status              = :status,
                    is_verified         = :is_verified,
                    updated_at          = :updated_at,
                    deleted_at          = :deleted_at
                WHERE id = :id AND user_id = :user_id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id'                  => $vehicle->getId(),
                'user_id'             => $vehicle->getUserId(),
                'vehicle_class_id'    => $vehicle->getVehicleClassId(),
                'vehicle_subclass_id' => $vehicle->getVehicleSubclassId(),
                'brand_id'            => $vehicle->getBrandId(),
                'model_id'            => $vehicle->getModelId(),
                'brand_name'          => $vehicle->getBrandName(),
                'model_name'          => $vehicle->getModelName(),
                'plate_number'        => $vehicle->getPlateNumber()->getValue(),
                'niv'                 => $vehicle->getNiv()->getValue(),
                'engine_serial'       => $vehicle->getEngineSerial()?->getValue(),
                'year'                => $vehicle->getYear(),
                'color'               => $vehicle->getColor(),
                'status'              => $vehicle->getStatus()->getValue(),
                'is_verified'         => $vehicle->isVerified() ? 1 : 0,
                'updated_at'          => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'deleted_at'          => $vehicle->getDeletedAt()?->format('Y-m-d H:i:s'),
            ]);
        }
    }

    public function findById(int $id): ?Vehicle
    {
        $stmt = $this->db->prepare("SELECT * FROM vehicles WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByUuid(Uuid $uuid): ?Vehicle
    {
        $stmt = $this->db->prepare("SELECT * FROM vehicles WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1");
        $stmt->execute(['uuid' => $uuid->getValue()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByPlateNumber(PlateNumber $plateNumber): ?Vehicle
    {
        $stmt = $this->db->prepare("SELECT * FROM vehicles WHERE plate_number = :plate AND deleted_at IS NULL LIMIT 1");
        $stmt->execute(['plate' => $plateNumber->getValue()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByNiv(Niv $niv): ?Vehicle
    {
        $stmt = $this->db->prepare("SELECT * FROM vehicles WHERE niv = :niv AND deleted_at IS NULL LIMIT 1");
        $stmt->execute(['niv' => $niv->getValue()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findAllByUserId(int $userId): array
    {
        $sql = "
            SELECT * FROM vehicles 
            WHERE user_id = :user_id AND deleted_at IS NULL 
            ORDER BY created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        $vehicles = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $vehicles[] = $this->hydrate($row);
        }

        return $vehicles;
    }

    public function softDeleteByUuid(Uuid $uuid, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE vehicles 
            SET deleted_at = CURRENT_TIMESTAMP, status = 'DISABLED' 
            WHERE uuid = :uuid AND user_id = :user_id AND deleted_at IS NULL
        ");
        $stmt->execute(['uuid' => $uuid->getValue(), 'user_id' => $userId]);

        return $stmt->rowCount() > 0;
    }

    private function hydrate(array $row): Vehicle
    {
        return new Vehicle(
            id: (int)$row['id'],
            uuid: new Uuid($row['uuid']),
            userId: (int)$row['user_id'],
            plateNumber: new PlateNumber($row['plate_number']),
            niv: new Niv($row['niv']),
            engineSerial: !empty($row['engine_serial']) ? new EngineSerial($row['engine_serial']) : null,
            year: (int)$row['year'],
            color: $row['color'],
            vehicleClassId: $row['vehicle_class_id'] !== null ? (int)$row['vehicle_class_id'] : null,
            vehicleSubclassId: $row['vehicle_subclass_id'] !== null ? (int)$row['vehicle_subclass_id'] : null,
            brandId: $row['brand_id'] !== null ? (int)$row['brand_id'] : null,
            modelId: $row['model_id'] !== null ? (int)$row['model_id'] : null,
            brandName: $row['brand_name'] ?? null,
            modelName: $row['model_name'] ?? null,
            status: VehicleStatus::fromString($row['status']),
            isVerified: (bool)$row['is_verified'],
            createdAt: !empty($row['created_at']) ? new DateTimeImmutable($row['created_at']) : null,
            updatedAt: !empty($row['updated_at']) ? new DateTimeImmutable($row['updated_at']) : null,
            deletedAt: !empty($row['deleted_at']) ? new DateTimeImmutable($row['deleted_at']) : null
        );
    }
}
