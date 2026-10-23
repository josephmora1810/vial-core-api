<?php

namespace App\Infrastructure\Database\Repositories\Vehicles;

use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Vehicles\Entities\Vehicle;
use App\Domain\Vehicles\Enums\VehicleStatus;
use App\Domain\Vehicles\Enums\VehicleType;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use App\Domain\Vehicles\ValueObjects\EngineSerial;
use App\Domain\Vehicles\ValueObjects\Niv;
use App\Domain\Vehicles\ValueObjects\PlateNumber;
use DateTimeImmutable;
use PDO;

class MariaDbVehicleRepository implements VehicleRepositoryInterface
{
    private const BASE_SELECT = "
        SELECT 
            v.*,
            m.name AS model_name,
            m.type AS vehicle_type,
            b.name AS brand_name,
            sc.name AS subclass_name,
            c.name AS class_name
        FROM vehicles v
        INNER JOIN vehicle_models m ON v.model_id = m.id
        INNER JOIN vehicle_brands b ON m.brand_id = b.id
        INNER JOIN vehicle_subclasses sc ON m.vehicle_subclass_id = sc.id
        INNER JOIN vehicle_classes c ON sc.vehicle_class_id = c.id
    ";

    public function __construct(private PDO $db) {}

    public function save(Vehicle $vehicle): void
    {
        if ($vehicle->getId() === null) {
            $sql = "
                INSERT INTO vehicles (
                    uuid,
                    user_id,
                    model_id,
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
                    :model_id,
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
                'uuid'          => $vehicle->getUuid()->getValue(),
                'user_id'       => $vehicle->getUserId(),
                'model_id'      => $vehicle->getModelId(),
                'plate_number'  => $vehicle->getPlateNumber()?->getValue(),
                'niv'           => $vehicle->getNiv()?->getValue(),
                'engine_serial' => $vehicle->getEngineSerial()?->getValue(),
                'year'          => $vehicle->getYear(),
                'color'         => $vehicle->getColor(),
                'status'        => $vehicle->getStatus()->getValue(),
                'is_verified'   => $vehicle->isVerified() ? 1 : 0,
                'created_at'    => $vehicle->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at'    => $vehicle->getUpdatedAt()?->format('Y-m-d H:i:s') ?? $vehicle->getCreatedAt()->format('Y-m-d H:i:s'),
                'deleted_at'    => $vehicle->getDeletedAt()?->format('Y-m-d H:i:s'),
            ]);

            $vehicle->setId((int)$this->db->lastInsertId());
        } else {
            $sql = "
                UPDATE vehicles SET
                    model_id      = :model_id,
                    plate_number  = :plate_number,
                    niv           = :niv,
                    engine_serial = :engine_serial,
                    year          = :year,
                    color         = :color,
                    status        = :status,
                    is_verified   = :is_verified,
                    updated_at    = :updated_at,
                    deleted_at    = :deleted_at
                WHERE id = :id AND user_id = :user_id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id'            => $vehicle->getId(),
                'user_id'       => $vehicle->getUserId(),
                'model_id'      => $vehicle->getModelId(),
                'plate_number'  => $vehicle->getPlateNumber()?->getValue(),
                'niv'           => $vehicle->getNiv()?->getValue(),
                'engine_serial' => $vehicle->getEngineSerial()?->getValue(),
                'year'          => $vehicle->getYear(),
                'color'         => $vehicle->getColor(),
                'status'        => $vehicle->getStatus()->getValue(),
                'is_verified'   => $vehicle->isVerified() ? 1 : 0,
                'updated_at'    => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'deleted_at'    => $vehicle->getDeletedAt()?->format('Y-m-d H:i:s'),
            ]);
        }
    }

    public function findById(int $id): ?Vehicle
    {
        $stmt = $this->db->prepare(self::BASE_SELECT . " WHERE v.id = :id AND v.deleted_at IS NULL LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByUuid(Uuid $uuid): ?Vehicle
    {
        $stmt = $this->db->prepare(self::BASE_SELECT . " WHERE v.uuid = :uuid AND v.deleted_at IS NULL LIMIT 1");
        $stmt->execute(['uuid' => $uuid->getValue()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByPlateNumber(PlateNumber $plateNumber): ?Vehicle
    {
        $stmt = $this->db->prepare(self::BASE_SELECT . " WHERE v.plate_number = :plate AND v.deleted_at IS NULL LIMIT 1");
        $stmt->execute(['plate' => $plateNumber->getValue()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByNiv(Niv $niv): ?Vehicle
    {
        $stmt = $this->db->prepare(self::BASE_SELECT . " WHERE v.niv = :niv AND v.deleted_at IS NULL LIMIT 1");
        $stmt->execute(['niv' => $niv->getValue()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findAllByUserId(int $userId): array
    {
        $stmt = $this->db->prepare(self::BASE_SELECT . " WHERE v.user_id = :user_id AND v.deleted_at IS NULL ORDER BY v.created_at DESC");
        $stmt->execute(['user_id' => $userId]);

        $vehicles = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $vehicles[] = $this->hydrate($row);
        }

        return $vehicles;
    }

    public function findCranesByUserId(int $userId): array
    {
        $stmt = $this->db->prepare(self::BASE_SELECT . " WHERE v.user_id = :user_id AND m.type = 'CRANE' AND v.deleted_at IS NULL ORDER BY v.created_at DESC");
        $stmt->execute(['user_id' => $userId]);

        $cranes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cranes[] = $this->hydrate($row);
        }

        return $cranes;
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

    public function findDetailedByUuid(Uuid $uuid): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM vw_vehicle_details WHERE uuid = :uuid LIMIT 1");
        $stmt->execute(['uuid' => $uuid->getValue()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findDetailedByPlateNumber(PlateNumber $plateNumber): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM vw_vehicle_details WHERE plate_number = :plate LIMIT 1");
        $stmt->execute(['plate' => $plateNumber->getValue()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findDetailedByUserId(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM vw_vehicle_details WHERE user_id = :user_id ORDER BY created_at DESC");
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function hydrate(array $row): Vehicle
    {
        return new Vehicle(
            id: (int)$row['id'],
            uuid: new Uuid($row['uuid']),
            userId: (int)$row['user_id'],
            modelId: (int)$row['model_id'],
            plateNumber: !empty($row['plate_number']) ? new PlateNumber($row['plate_number']) : null,
            niv: !empty($row['niv']) ? new Niv($row['niv']) : null,
            engineSerial: !empty($row['engine_serial']) ? new EngineSerial($row['engine_serial']) : null,
            year: (int)$row['year'],
            color: $row['color'],
            status: VehicleStatus::fromString($row['status']),
            isVerified: (bool)$row['is_verified'],
            brandName: $row['brand_name'] ?? null,
            modelName: $row['model_name'] ?? null,
            className: $row['class_name'] ?? null,
            subclassName: $row['subclass_name'] ?? null,
            type: !empty($row['vehicle_type']) ? VehicleType::fromString($row['vehicle_type']) : VehicleType::Car,
            createdAt: !empty($row['created_at']) ? new DateTimeImmutable($row['created_at']) : null,
            updatedAt: !empty($row['updated_at']) ? new DateTimeImmutable($row['updated_at']) : null,
            deletedAt: !empty($row['deleted_at']) ? new DateTimeImmutable($row['deleted_at']) : null
        );
    }
}
