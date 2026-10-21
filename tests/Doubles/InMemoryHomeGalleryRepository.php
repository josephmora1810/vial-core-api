<?php

namespace Tests\Doubles;

use App\Domain\Landing\Entities\HomeGallery;
use App\Domain\Landing\Repositories\HomeGalleryRepositoryInterface;

class InMemoryHomeGalleryRepository implements HomeGalleryRepositoryInterface
{
    /** @var array<int, HomeGallery> */
    private array $items = [];
    private int $nextId = 1;

    public function save(HomeGallery $item, ?string $imagePath = null): void
    {
        if ($item->getId() === null) {
            $item->setId($this->nextId++);
        }

        if ($imagePath !== null) {
            $item->setImageUrl($imagePath);
        }

        $this->items[$item->getId()] = $item;
    }

    public function findById(int $id): ?HomeGallery
    {
        return $this->items[$id] ?? null;
    }

    public function findAllActive(): array
    {
        $filtered = array_filter($this->items, fn(HomeGallery $i) => $i->isActive());
        usort($filtered, fn(HomeGallery $a, HomeGallery $b) => $a->getOrderIndex() <=> $b->getOrderIndex());
        return array_values($filtered);
    }

    public function findAll(): array
    {
        $items = array_values($this->items);
        usort($items, fn(HomeGallery $a, HomeGallery $b) => $a->getOrderIndex() <=> $b->getOrderIndex());
        return $items;
    }

    public function delete(int $id): bool
    {
        if (isset($this->items[$id])) {
            unset($this->items[$id]);
            return true;
        }
        return false;
    }
}
