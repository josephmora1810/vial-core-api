<?php

namespace App\Domain\Landing\Repositories;

use App\Domain\Landing\Entities\HomeGallery;

interface HomeGalleryRepositoryInterface
{
    public function save(HomeGallery $item, ?string $imagePath = null): void;

    public function findById(int $id): ?HomeGallery;

    /**
     * @return list<HomeGallery>
     */
    public function findAllActive(): array;

    /**
     * @return list<HomeGallery>
     */
    public function findAll(): array;

    public function delete(int $id): bool;
}
