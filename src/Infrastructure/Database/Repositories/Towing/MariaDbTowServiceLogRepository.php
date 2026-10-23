<?php

namespace App\Infrastructure\Database\Repositories\Towing;

use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Towing\Entities\TowServiceLog;
use App\Domain\Towing\Repositories\TowServiceLogRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

class MariaDbTowServiceLogRepository implements TowServiceLogRepositoryInterface
{
    public function __construct(private PDO $db) {}

    /** Guarda un nuevo pulso GPS en tow_service_logs (append-only) */
    public function append(TowServiceLog $log): void
    {
        if ($log->getId() !== null) {
            throw new InvalidArgumentException('TowServiceLog id must be null when appending a new log.');
        }

        $sql = "
            INSERT INTO tow_service_logs (
                tow_service_id, 
                lat, 
                lng, 
                speed_kmh, 
                created_at
            ) VALUES (
                :tow_service_id, 
                :lat, 
                :lng, 
                :speed_kmh, 
                :created_at
            )
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tow_service_id' => $log->getTowServiceId(),
            ':lat'            => $log->getCoordinates()->getLatitude(),
            ':lng'            => $log->getCoordinates()->getLongitude(),
            ':speed_kmh'      => $log->getSpeedKmh(),
            ':created_at'     => $log->getCreatedAt()?->format('Y-m-d H:i:s') ?? date('Y-m-d H:i:s'),
        ]);
        $log->setId((int)$this->db->lastInsertId());
    }

    /** Retorna la última ubicación registrada de la grúa para un servicio */
    public function getLatestByServiceId(int $towServiceId): ?TowServiceLog
    {
        $sql = "SELECT * FROM tow_service_logs WHERE tow_service_id = :service_id ORDER BY id DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':service_id' => $towServiceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    /** 
     * Retorna la traza completa de puntos GPS para dibujar la ruta recorrida en el mapa 
     * @return list<TowServiceLog>
     */
    public function getHistoryByServiceId(int $towServiceId): array
    {
        $sql = "SELECT * FROM tow_service_logs WHERE tow_service_id = :service_id ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':service_id' => $towServiceId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $logs = [];
        foreach ($rows as $row) {
            $logs[] = $this->mapRow($row);
        }

        return $logs;
    }

    private function mapRow(array $row): TowServiceLog
    {
        return new TowServiceLog(
            id: (int)$row['id'],
            towServiceId: (int)$row['tow_service_id'],
            coordinates: new Coordinates((float)$row['lat'], (float)$row['lng']),
            speedKmh: (float)$row['speed_kmh'],
            createdAt: !empty($row['created_at']) ? new DateTimeImmutable($row['created_at']) : null
        );
    }
}
