<?php

namespace App\Application\Landing\UseCases;

use App\Domain\Landing\Entities\HomeGallery;
use App\Domain\Landing\Repositories\HomeGalleryRepositoryInterface;

class GetActiveHomeGallery
{
    public function __construct(
        private HomeGalleryRepositoryInterface $galleryRepository
    ) {}

    /**
     * Retorna los banners activos de la Landing Page ordenados para la web de Vue.js (FR-36).
     *
     * @return list<HomeGallery>
     */
    public function execute(): array
    {
        return $this->galleryRepository->findAllActive();
    }
}
