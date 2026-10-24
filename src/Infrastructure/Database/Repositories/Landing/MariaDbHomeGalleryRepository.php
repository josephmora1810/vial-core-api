<?php

namespace App\Infrastructure\Database\Repositories\Landing;

use App\Domain\Landing\{
    Entities\HomeGallery,
    Repositories\HomeGalleryRepositoryInterface
};
use App\Domain\Shared\Repositories\ImageRepositoryInterface;
use DateTimeImmutable;
use PDO;

class MariaDbHomeGalleryRepository implements HomeGalleryRepositoryInterface
{
    public function __construct(
        private PDO $db,
        private ?ImageRepositoryInterface $imageRepository = null
    ) {}

    public function save(HomeGallery $item, ?string $imagePath = null): void
    {
        if ($item->getId() === null) {
            $sql = "
                INSERT INTO home_galleries (
                    title, subtitle, description, button_text, button_link, order_index, is_active, created_at, updated_at
                ) VALUES (
                    :title, :subtitle, :description, :button_text, :button_link, :order_index, :is_active, :created_at, :updated_at
                )
            ";

            $now = new DateTimeImmutable();
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'title'       => $item->getTitle(),
                'subtitle'    => $item->getSubtitle(),
                'description' => $item->getDescription(),
                'button_text' => $item->getButtonText(),
                'button_link' => $item->getButtonLink(),
                'order_index' => $item->getOrderIndex(),
                'is_active'   => $item->isActive() ? 1 : 0,
                'created_at'  => $now->format('Y-m-d H:i:s'),
                'updated_at'  => $now->format('Y-m-d H:i:s'),
            ]);

            $item->setId((int)$this->db->lastInsertId());
        } else {
            $sql = "
                UPDATE home_galleries SET
                    title       = :title,
                    subtitle    = :subtitle,
                    description = :description,
                    button_text = :button_text,
                    button_link = :button_link,
                    order_index = :order_index,
                    is_active   = :is_active,
                    updated_at  = :updated_at
                WHERE id = :id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'title'       => $item->getTitle(),
                'subtitle'    => $item->getSubtitle(),
                'description' => $item->getDescription(),
                'button_text' => $item->getButtonText(),
                'button_link' => $item->getButtonLink(),
                'order_index' => $item->getOrderIndex(),
                'is_active'   => $item->isActive() ? 1 : 0,
                'updated_at'  => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'id'          => $item->getId(),
            ]);
        }

        // Si se suministra una nueva URL o ruta de imagen, registrarla en la tabla polimórfica images
        $finalImage = $imagePath ?? $item->getImageUrl();
        if ($finalImage !== null && $this->imageRepository !== null && $item->getId() !== null) {
            $this->imageRepository->save(
                imageableType: 'home_gallery',
                imageableId: $item->getId(),
                type: 'gallery_banner',
                path: $finalImage
            );
            $item->setImageUrl($finalImage);
        }
    }

    public function findById(int $id): ?HomeGallery
    {
        $sql = "
            SELECT g.*, img.path AS image_url
            FROM home_galleries g
            LEFT JOIN images img ON img.imageable_type = 'home_gallery' 
                                AND img.imageable_id = g.id 
                                AND img.type = 'gallery_banner'
            WHERE g.id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findAllActive(): array
    {
        $sql = "
            SELECT g.*, img.path AS image_url
            FROM home_galleries g
            LEFT JOIN images img ON img.imageable_type = 'home_gallery' 
                                AND img.imageable_id = g.id 
                                AND img.type = 'gallery_banner'
            WHERE g.is_active = 1
            ORDER BY g.order_index ASC, g.id ASC
        ";

        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findAll(): array
    {
        $sql = "
            SELECT g.*, img.path AS image_url
            FROM home_galleries g
            LEFT JOIN images img ON img.imageable_type = 'home_gallery' 
                                AND img.imageable_id = g.id 
                                AND img.type = 'gallery_banner'
            ORDER BY g.order_index ASC, g.id ASC
        ";

        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function delete(int $id): bool
    {
        // 1. Eliminar referencias en images
        $this->imageRepository?->deleteByImageable('home_gallery', $id);

        // 2. Eliminar registro en home_galleries
        $stmt = $this->db->prepare("DELETE FROM home_galleries WHERE id = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    private function hydrate(array $row): HomeGallery
    {
        return new HomeGallery(
            id: (int)$row['id'],
            title: $row['title'],
            subtitle: $row['subtitle'] ?? null,
            description: $row['description'] ?? null,
            buttonText: $row['button_text'] ?? null,
            buttonLink: $row['button_link'] ?? null,
            orderIndex: (int)$row['order_index'],
            isActive: (bool)$row['is_active'],
            imageUrl: $row['image_url'] ?? null,
            createdAt: !empty($row['created_at']) ? new DateTimeImmutable($row['created_at']) : null,
            updatedAt: !empty($row['updated_at']) ? new DateTimeImmutable($row['updated_at']) : null
        );
    }
}
