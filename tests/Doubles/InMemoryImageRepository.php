<?php

namespace Tests\Doubles;

use App\Domain\Shared\Repositories\ImageRepositoryInterface;

class InMemoryImageRepository implements ImageRepositoryInterface
{
    /** @var array<int, array{id: int, imageable_type: string, imageable_id: int, type: string, path: string, created_at: string}> */
    private array $images = [];
    private int $nextId = 1;

    public function save(string $imageableType, int $imageableId, string $type, string $path): int
    {
        // Relación 1 a 1 polimórfica: si ya existe para esa entidad, actualizarla
        foreach ($this->images as $id => $img) {
            if ($img['imageable_type'] === $imageableType && $img['imageable_id'] === $imageableId) {
                $this->images[$id]['type'] = $type;
                $this->images[$id]['path'] = $path;
                return $id;
            }
        }

        $id = $this->nextId++;
        $this->images[$id] = [
            'id'             => $id,
            'imageable_type' => $imageableType,
            'imageable_id'   => $imageableId,
            'type'           => $type,
            'path'           => $path,
            'created_at'     => date('Y-m-d H:i:s'),
        ];

        return $id;
    }

    public function findByImageable(string $imageableType, int $imageableId, ?string $type = null): array
    {
        $result = [];
        foreach ($this->images as $img) {
            if ($img['imageable_type'] === $imageableType && $img['imageable_id'] === $imageableId) {
                if ($type === null || $img['type'] === $type) {
                    $result[] = $img;
                }
            }
        }
        return array_reverse($result);
    }

    public function findLatestByImageable(string $imageableType, int $imageableId, ?string $type = null): ?array
    {
        $items = $this->findByImageable($imageableType, $imageableId, $type);
        return $items[0] ?? null;
    }

    public function deleteByImageable(string $imageableType, int $imageableId, ?string $type = null): bool
    {
        $deleted = false;
        foreach ($this->images as $id => $img) {
            if ($img['imageable_type'] === $imageableType && $img['imageable_id'] === $imageableId) {
                if ($type === null || $img['type'] === $type) {
                    unset($this->images[$id]);
                    $deleted = true;
                }
            }
        }
        return $deleted;
    }

    public function all(): array
    {
        return array_values($this->images);
    }
}
