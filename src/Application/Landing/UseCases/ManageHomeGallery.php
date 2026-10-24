<?php

namespace App\Application\Landing\UseCases;

use App\Application\Landing\DTOs\HomeGalleryDeletedResponseDto;
use App\Domain\Landing\{
    Entities\HomeGallery,
    Repositories\HomeGalleryRepositoryInterface
};
use App\Domain\Shared\Services\StorageServiceInterface;
use Exception;

class ManageHomeGallery
{
    public function __construct(
        private HomeGalleryRepositoryInterface $galleryRepository,
        private StorageServiceInterface $storageService
    ) {}

    /**
     * @return list<HomeGallery>
     */
    public function listAll(): array
    {
        return $this->galleryRepository->findAll();
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
    ): HomeGallery {
        if (trim($title) === '') {
            throw new Exception('El título del banner de galería es obligatorio.', 422);
        }

        // Si se subió un archivo local, subirlo a S3
        $finalImageUrl = $imageUrl;
        if (!empty($localImageFilePath)) {
            $finalImageUrl = $this->uploadLocalImage($localImageFilePath);
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

        return $item;
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
    ): HomeGallery {
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
        if (!empty($localImageFilePath)) {
            $newImageUrl = $this->uploadLocalImage($localImageFilePath);
            $item->setImageUrl($newImageUrl);
        }

        $this->galleryRepository->save($item, $newImageUrl);

        return $item;
    }

    public function delete(int $id): HomeGalleryDeletedResponseDto
    {
        $item = $this->galleryRepository->findById($id);
        if ($item === null) {
            throw new Exception('Banner de galería no encontrado.', 404);
        }

        return new HomeGalleryDeletedResponseDto(
            deleted: $this->galleryRepository->delete($id),
            id: $id
        );
    }

    /**
     * Valida la ruta local (blindaje CWE-73 / CWE-59), la sube al almacenamiento remoto
     * y elimina el archivo local. Retorna la URL final de la imagen.
     */
    private function uploadLocalImage(string $localImageFilePath): string
    {
        if (!file_exists($localImageFilePath) || !is_readable($localImageFilePath)) {
            throw new Exception('El archivo de imagen no fue encontrado o es ilegible.', 400);
        }

        // Blindaje contra Path Traversal / CWE-73 / CWE-59: Solo permitir procesar y eliminar archivos en storage o temp
        $realPath = realpath($localImageFilePath);
        $storageDir = realpath(dirname(__DIR__, 4) . '/storage');
        $tempDir = realpath(sys_get_temp_dir());
        $isSafeStorage = ($storageDir !== false && $realPath !== false && str_starts_with($realPath, $storageDir));
        $isSafeTemp = ($tempDir !== false && $realPath !== false && str_starts_with($realPath, $tempDir));

        if (!$isSafeStorage && !$isSafeTemp) {
            throw new Exception('Acceso denegado: la ruta del archivo no está autorizada para carga de imágenes.', 403);
        }

        $ext = strtolower(pathinfo($localImageFilePath, PATHINFO_EXTENSION)) ?: 'jpg';
        $s3Key = sprintf('gallery/banner_%s.%s', bin2hex(random_bytes(6)), $ext);
        $url = $this->storageService->upload($localImageFilePath, $s3Key);
        if (is_file($localImageFilePath)) {
            @unlink($localImageFilePath);
        }

        return $url;
    }
}
