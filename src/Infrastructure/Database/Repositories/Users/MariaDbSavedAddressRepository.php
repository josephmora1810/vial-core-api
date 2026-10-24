<?php

namespace App\Infrastructure\Database\Repositories\Users;

use App\Domain\Shared\ValueObjects\{Coordinates, Uuid};
use App\Domain\Users\{
    Entities\SavedAddress,
    Repositories\SavedAddressRepositoryInterface
};
use DateTimeImmutable;
use PDO;

class MariaDbSavedAddressRepository implements SavedAddressRepositoryInterface
{
    public function __construct(private PDO $db) {}

    public function save(SavedAddress $address): void
    {
        if ($address->getId() === null) {
            $sql = "
                INSERT INTO saved_addresses (
                    uuid,
                    user_id,
                    label,
                    address_line,
                    latitude,
                    longitude,
                    is_favorite,
                    created_at,
                    updated_at
                ) VALUES (
                    :uuid,
                    :user_id,
                    :label,
                    :address_line,
                    :latitude,
                    :longitude,
                    :is_favorite,
                    :created_at,
                    :updated_at
                )
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'uuid'         => $address->getUuid()->getValue(),
                'user_id'      => $address->getUserId(),
                'label'        => $address->getLabel(),
                'address_line' => $address->getAddressLine(),
                'latitude'     => $address->getCoordinates()->getLatitude(),
                'longitude'    => $address->getCoordinates()->getLongitude(),
                'is_favorite'  => $address->isFavorite() ? 1 : 0,
                'created_at'   => $address->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at'   => $address->getUpdatedAt()?->format('Y-m-d H:i:s') ?? $address->getCreatedAt()->format('Y-m-d H:i:s'),
            ]);

            $address->setId((int)$this->db->lastInsertId());
        } else {
            $sql = "
                UPDATE saved_addresses SET
                    label        = :label,
                    address_line = :address_line,
                    latitude     = :latitude,
                    longitude    = :longitude,
                    is_favorite  = :is_favorite,
                    updated_at   = :updated_at
                WHERE id = :id AND user_id = :user_id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id'           => $address->getId(),
                'user_id'      => $address->getUserId(),
                'label'        => $address->getLabel(),
                'address_line' => $address->getAddressLine(),
                'latitude'     => $address->getCoordinates()->getLatitude(),
                'longitude'    => $address->getCoordinates()->getLongitude(),
                'is_favorite'  => $address->isFavorite() ? 1 : 0,
                'updated_at'   => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }
    }

    public function findById(int $id): ?SavedAddress
    {
        $stmt = $this->db->prepare("SELECT * FROM saved_addresses WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByUuid(Uuid $uuid): ?SavedAddress
    {
        $stmt = $this->db->prepare("SELECT * FROM saved_addresses WHERE uuid = :uuid LIMIT 1");
        $stmt->execute(['uuid' => $uuid->getValue()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findAllByUserId(int $userId): array
    {
        $sql = "
            SELECT * FROM saved_addresses 
            WHERE user_id = :user_id 
            ORDER BY is_favorite DESC, created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        $addresses = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $addresses[] = $this->hydrate($row);
        }

        return $addresses;
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM saved_addresses WHERE id = :id AND user_id = :user_id");
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public function deleteByUuid(Uuid $uuid, int $userId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM saved_addresses WHERE uuid = :uuid AND user_id = :user_id");
        $stmt->execute(['uuid' => $uuid->getValue(), 'user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    private function hydrate(array $row): SavedAddress
    {
        return new SavedAddress(
            id: (int)$row['id'],
            uuid: new Uuid($row['uuid']),
            userId: (int)$row['user_id'],
            label: $row['label'],
            addressLine: $row['address_line'],
            coordinates: new Coordinates((float)$row['latitude'], (float)$row['longitude']),
            isFavorite: (bool)$row['is_favorite'],
            createdAt: !empty($row['created_at']) ? new DateTimeImmutable($row['created_at']) : null,
            updatedAt: !empty($row['updated_at']) ? new DateTimeImmutable($row['updated_at']) : null
        );
    }
}
