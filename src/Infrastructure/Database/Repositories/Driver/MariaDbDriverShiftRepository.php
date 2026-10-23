<?php

namespace App\Infrastructure\Database\Repositories\Driver;

use App\Domain\Driver\Entities\DriverShift;
use App\Domain\Driver\Repositories\DriverShiftRepositoryInterface;
use DateTimeImmutable;
use PDO;

class MariaDbDriverShiftRepository implements DriverShiftRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function save(DriverShift $shift): DriverShift
    {
        if ($shift->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO driver_shifts (driver_id, crane_id, is_active, started_at, ended_at, last_latitude, last_longitude, last_ping_at)
                VALUES (:driver_id, :crane_id, :is_active, :started_at, :ended_at, :last_latitude, :last_longitude, :last_ping_at)
            ");
            $stmt->execute([
                'driver_id'      => $shift->getDriverId(),
                'crane_id'       => $shift->getCraneId(),
                'is_active'      => $shift->isActive() ? 1 : 0,
                'started_at'     => $shift->getStartedAt()->format('Y-m-d H:i:s'),
                'ended_at'       => $shift->getEndedAt()?->format('Y-m-d H:i:s'),
                'last_latitude'  => $shift->getLastLatitude(),
                'last_longitude' => $shift->getLastLongitude(),
                'last_ping_at'   => $shift->getLastPingAt()?->format('Y-m-d H:i:s'),
            ]);

            $id = (int)$this->pdo->lastInsertId();
            return new DriverShift(
                id: $id,
                driverId: $shift->getDriverId(),
                craneId: $shift->getCraneId(),
                isActive: $shift->isActive(),
                startedAt: $shift->getStartedAt(),
                endedAt: $shift->getEndedAt(),
                lastLatitude: $shift->getLastLatitude(),
                lastLongitude: $shift->getLastLongitude(),
                lastPingAt: $shift->getLastPingAt()
            );
        }

        $stmt = $this->pdo->prepare("
            UPDATE driver_shifts SET
                crane_id = :crane_id,
                is_active = :is_active,
                ended_at = :ended_at,
                last_latitude = :last_latitude,
                last_longitude = :last_longitude,
                last_ping_at = :last_ping_at
            WHERE id = :id
        ");
        $stmt->execute([
            'id'             => $shift->getId(),
            'crane_id'       => $shift->getCraneId(),
            'is_active'      => $shift->isActive() ? 1 : 0,
            'ended_at'       => $shift->getEndedAt()?->format('Y-m-d H:i:s'),
            'last_latitude'  => $shift->getLastLatitude(),
            'last_longitude' => $shift->getLastLongitude(),
            'last_ping_at'   => $shift->getLastPingAt()?->format('Y-m-d H:i:s'),
        ]);

        return $shift;
    }

    public function findActiveByDriverId(int $driverId): ?DriverShift
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM driver_shifts 
            WHERE driver_id = :driver_id AND is_active = 1 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute(['driver_id' => $driverId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRowToEntity($row) : null;
    }

    public function findActiveByCraneId(int $craneId): ?DriverShift
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM driver_shifts 
            WHERE crane_id = :crane_id AND is_active = 1 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute(['crane_id' => $craneId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRowToEntity($row) : null;
    }

    public function endActiveShift(int $driverId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE driver_shifts 
            SET is_active = 0, ended_at = NOW() 
            WHERE driver_id = :driver_id AND is_active = 1
        ");
        $stmt->execute(['driver_id' => $driverId]);
    }

    public function updateLocation(int $driverId, float $latitude, float $longitude): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE driver_shifts 
            SET last_latitude = :lat, last_longitude = :lng, last_ping_at = NOW() 
            WHERE driver_id = :driver_id AND is_active = 1
        ");
        $stmt->execute([
            'driver_id' => $driverId,
            'lat'       => $latitude,
            'lng'       => $longitude,
        ]);
    }

    public function findNearbyActiveDrivers(float $latitude, float $longitude, float $radiusKm = 25.0): array
    {
        // Fórmula de Haversine para encontrar grúas activas cercanas (NFR-02)
        $sql = "
            SELECT 
                ds.id AS shift_id,
                ds.driver_id,
                ds.crane_id,
                ds.last_latitude,
                ds.last_longitude,
                ds.last_ping_at,
                u.names AS driver_names,
                u.surnames AS driver_surnames,
                u.phone AS driver_phone,
                v.plate_number AS crane_plate,
                (6371 * acos(
                    cos(radians(:origin_lat)) * cos(radians(ds.last_latitude)) *
                    cos(radians(ds.last_longitude) - radians(:origin_lng)) +
                    sin(radians(:origin_lat)) * sin(radians(ds.last_latitude))
                )) AS distance_km
            FROM driver_shifts ds
            INNER JOIN users u ON ds.driver_id = u.id
            INNER JOIN vehicles v ON ds.crane_id = v.id
            WHERE ds.is_active = 1
              AND ds.last_latitude IS NOT NULL
              AND ds.last_longitude IS NOT NULL
              AND ds.last_ping_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            HAVING distance_km <= :radius_km
            ORDER BY distance_km ASC;
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'origin_lat' => $latitude,
            'origin_lng' => $longitude,
            'radius_km'  => $radiusKm,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRowToEntity(array $row): DriverShift
    {
        return new DriverShift(
            id: (int)$row['id'],
            driverId: (int)$row['driver_id'],
            craneId: (int)$row['crane_id'],
            isActive: (bool)$row['is_active'],
            startedAt: new DateTimeImmutable((string)$row['started_at']),
            endedAt: !empty($row['ended_at']) ? new DateTimeImmutable((string)$row['ended_at']) : null,
            lastLatitude: $row['last_latitude'] !== null ? (float)$row['last_latitude'] : null,
            lastLongitude: $row['last_longitude'] !== null ? (float)$row['last_longitude'] : null,
            lastPingAt: !empty($row['last_ping_at']) ? new DateTimeImmutable((string)$row['last_ping_at']) : null
        );
    }
}
