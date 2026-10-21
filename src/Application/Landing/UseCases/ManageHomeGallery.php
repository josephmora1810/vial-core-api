<?php

namespace App\Application\Landing\UseCases;

use App\Domain\Landing\Entities\HomeGallery;
use App\Domain\Landing\Repositories\HomeGalleryRepositoryInterface;
use App\Domain\Shared\Services\StorageServiceInterface;
use Exception;

class ManageHomeGallery
{
    public function __construct(
        private HomeGalleryRepositoryInterface $galleryRepository,
        private StorageServiceInterface $storageService
    ) {}

    public function listAll(): array
    {
        $items = $this->galleryRepository->findAll();
        return array_map(fn($item) => $item->toArray(), $items);
    }

    public function create(
        string $title,
        ?string $subtitle = null,
        ?string $description = null,
        ?string $buttonText = null,
        ?string $buttonLink = null,
        int $orderIndex = 0,
        bool $isActive = true,
        ?string $localImageFilePath = null,
        ?string $imageUrl = null
    ): array {
        if (trim($title) === '') {
            throw new Exception('El título del banner de galería es obligatorio.', 422);
        }

        // Si se subió un archivo local, subirlo a S3
        $finalImageUrl = $imageUrl;
        if (!empty($localImageFilePath) && file_exists($localImageFilePath)) {
            $ext = strtolower(pathinfo($localImageFilePath, PATHINFO_EXTENSION)) ?: 'jpg';
            $s3Key = sprintf('gallery/banner_%s.%s', bin2hex(random_bytes(6)), $ext);
            $finalImageUrl = $this->storageService->upload($localImageFilePath, $s3Key);
            if (is_file($localImageFilePath)) {
                @unlink($localImageFilePath);
            }
        }

        $item = HomeGallery::create(
            title: $title,
            subtitle: $subtitle,
            description: $description,
            buttonText: $buttonText,
            buttonLink: $buttonLink,
            orderIndex: $orderIndex,
            isActive: $isActive,
            imageUrl: $finalImageUrl
        );

        $this->galleryRepository->save($item, $finalImageUrl);

        return $item->toArray();
    }

    public function update(
        int $id,
        ?string $title = null,
        ?string $subtitle = null,
        ?string $description = null,
        ?string $buttonText = null,
        ?string $buttonLink = null,
        ?int $orderIndex = null,
        ?bool $isActive = null,
        ?string $localImageFilePath = null,
        ?string $imageUrl = null
    ): array {
        $item = $this->galleryRepository->findById($id);
        if ($item === null) {
            throw new Exception('Banner de galería no encontrado.', 404);
        }

        if ($title !== null) $item->setTitle($title);
        if ($subtitle !== null) $item->setSubtitle($subtitle);
        if ($description !== null) $item->setDescription($description);
        if ($buttonText !== null) $item->setButtonText($buttonText);
        if ($buttonLink !== null) $item->setButtonLink($buttonLink);
        if ($orderIndex !== null) $item->setOrderIndex($orderIndex);
        if ($isActive !== null) {
            $isActive ? $item->activate() : $item->deactivate();
        }

        $newImageUrl = $imageUrl;
        if (!empty($localImageFilePath) && file_exists($localImageFilePath)) {
            $ext = strtolower(pathinfo($localImageFilePath, PATHINFO_EXTENSION)) ?: 'jpg';
            $s3Key = sprintf('gallery/banner_%s.%s', bin2hex(random_bytes(6)), $ext);
            $newImageUrl = $this->storageService->upload($localImageFilePath, $s3Key);
            if (is_file($localImageFilePath)) {
                @unlink($localImageFilePath);
            }
            $item->setImageUrl($newImageUrl);
        }

        $this->galleryRepository->save($item, $newImageUrl);

        return $item->toArray();
    }

    public function delete(int $id): bool
    {
        $item = $this->galleryRepository->findById($id);
        if ($item === null) {
            throw new Exception('Banner de galería no encontrado.', 404);
        }

        return $this->galleryRepository->delete($id);
    }
}
