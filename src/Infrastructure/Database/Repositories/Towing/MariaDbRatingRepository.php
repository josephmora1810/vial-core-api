<?php

namespace App\Infrastructure\Database\Repositories\Towing;

use App\Domain\Towing\Entities\Rating;
use App\Domain\Towing\Repositories\RatingRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

class MariaDbRatingRepository implements RatingRepositoryInterface
{
    public function __construct(private PDO $db) {}

    public function save(Rating $rating): void
    {
        if ($rating->getId() !== null) {
            throw new InvalidArgumentException('El Rating debe ser nulo para crear uno nuevo');
        }

        $sql = "
            INSERT INTO ratings (
                tow_service_id, 
                user_id, 
                rated_user_id, 
                rating, 
                comment, 
                created_at
            ) VALUES (
                :tow_service_id, 
                :user_id, 
                :rated_user_id, 
                :rating, 
                :comment, 
                :created_at
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tow_service_id' => $rating->getTowServiceId(),
            ':user_id'        => $rating->getUserId(),
            ':rated_user_id'  => $rating->getRatedUserId(),
            ':rating'         => $rating->getRating(),
            ':comment'        => $rating->getComment(),
            ':created_at'     => $rating->getCreatedAt()?->format('Y-m-d H:i:s') ?? date('Y-m-d H:i:s'),
        ]);

        $rating->setId((int)$this->db->lastInsertId());
    }

    /** Verifica si el usuario ya calificó este servicio (evita doble calificación) */
    public function findByServiceAndUser(int $towServiceId, int $userId): ?Rating
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM ratings WHERE tow_service_id = :tow_service_id AND user_id = :user_id LIMIT 1'
        );
        $stmt->execute([
            ':tow_service_id' => $towServiceId,
            ':user_id'        => $userId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    /** Retorna todas las calificaciones de un usuario (para chofer o cliente) */
    public function findByRatedUserId(int $ratedUserId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM ratings WHERE rated_user_id = :rated_user_id ORDER BY created_at DESC'
        );
        $stmt->execute([':rated_user_id' => $ratedUserId]);

        $ratings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ratings[] = $this->mapRow($row);
        }

        return $ratings;
    }

    /** Calcula el promedio de estrellas de un usuario (ej. 4.85) */
    public function getAverageRatingForUser(int $ratedUserId): float
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(ROUND(AVG(rating), 2), 5.0) FROM ratings WHERE rated_user_id = :rated_user_id'
        );
        $stmt->execute([':rated_user_id' => $ratedUserId]);

        return (float)$stmt->fetchColumn();
    }

    private function mapRow(array $row): Rating
    {
        return new Rating(
            id: (int)$row['id'],
            towServiceId: (int)$row['tow_service_id'],
            userId: (int)$row['user_id'],
            ratedUserId: (int)$row['rated_user_id'],
            rating: (int)$row['rating'],
            comment: $row['comment'] ?? null,
            createdAt: !empty($row['created_at']) ? new DateTimeImmutable($row['created_at']) : null
        );
    }
}
