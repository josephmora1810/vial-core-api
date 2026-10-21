<?php

namespace App\Infrastructure\Database\Repositories\Shared;

use App\Domain\Shared\Repositories\ImageRepositoryInterface;
use PDO;

class MariaDbImageRepository implements ImageRepositoryInterface
{
    public function __construct(private PDO $db) {}

    public function save(string $imageableType, int $imageableId, string $type, string $path): int
    {
        // Relación 1 a 1 polimórfica: si ya existe una imagen previa para esta entidad, se sobrescribe
        $stmtCheck = $this->db->prepare("
            SELECT id FROM images 
            WHERE imageable_type = :imageable_type 
              AND imageable_id = :imageable_id 
            LIMIT 1
        ");
        $stmtCheck->execute([
            'imageable_type' => $imageableType,
            'imageable_id'   => $imageableId,
        ]);
        $existingId = $stmtCheck->fetchColumn();

        if ($existingId !== false && $existingId !== null) {
            $stmtUpdate = $this->db->prepare("
                UPDATE images 
                SET type = :type, path = :path, updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                'type' => $type,
                'path' => $path,
                'id'   => (int)$existingId,
            ]);

            return (int)$existingId;
        }

        $stmtInsert = $this->db->prepare("
            INSERT INTO images (imageable_type, imageable_id, type, path)
            VALUES (:imageable_type, :imageable_id, :type, :path)
            ON DUPLICATE KEY UPDATE type = VALUES(type), path = VALUES(path), updated_at = CURRENT_TIMESTAMP
        ");
        $stmtInsert->execute([
            'imageable_type' => $imageableType,
            'imageable_id'   => $imageableId,
            'type'           => $type,
            'path'           => $path,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function findByImageable(string $imageableType, int $imageableId, ?string $type = null): array
    {
        $sql = "
            SELECT id, imageable_type, imageable_id, type, path, created_at 
            FROM images 
            WHERE imageable_type = :imageable_type AND imageable_id = :imageable_id
        ";
        $params = [
            'imageable_type' => $imageableType,
            'imageable_id'   => $imageableId,
        ];

        if ($type !== null) {
            $sql .= " AND type = :type";
            $params['type'] = $type;
        }

        $sql .= " ORDER BY id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findLatestByImageable(string $imageableType, int $imageableId, ?string $type = null): ?array
    {
        $items = $this->findByImageable($imageableType, $imageableId, $type);
        return $items[0] ?? null;
    }

    public function deleteByImageable(string $imageableType, int $imageableId, ?string $type = null): bool
    {
        $sql = "DELETE FROM images WHERE imageable_type = :imageable_type AND imageable_id = :imageable_id";
        $params = [
            'imageable_type' => $imageableType,
            'imageable_id'   => $imageableId,
        ];

        if ($type !== null) {
            $sql .= " AND type = :type";
            $params['type'] = $type;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }
}
