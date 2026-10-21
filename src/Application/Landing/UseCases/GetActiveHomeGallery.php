<?php

namespace App\Application\Landing\UseCases;

use App\Domain\Landing\Repositories\HomeGalleryRepositoryInterface;

class GetActiveHomeGallery
{
    public function __construct(
        private HomeGalleryRepositoryInterface $galleryRepository
    ) {}

    /**
     * Retorna los banners activos de la Landing Page ordenados para la web de Vue.js (FR-36).
     *
     * @return list<array<string, mixed>>
     */
    public function execute(): array
    {
        $items = $this->galleryRepository->findAllActive();
        return array_map(fn($item) => $item->toArray(), $items);
    }
}
