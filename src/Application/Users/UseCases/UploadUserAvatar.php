<?php

namespace App\Application\Users\UseCases;

use App\Domain\Shared\Repositories\ImageRepositoryInterface;
use App\Domain\Shared\Services\StorageServiceInterface;
use App\Domain\Users\Repositories\UserRepositoryInterface;
use Exception;

class UploadUserAvatar
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private StorageServiceInterface $storageService,
        private ImageRepositoryInterface $imageRepository
    ) {}

    public function execute(int $userId, string $localFilePath): array
    {
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new Exception('Usuario no encontrado.', 404);
        }

        if (!file_exists($localFilePath) || !is_readable($localFilePath)) {
            throw new Exception('El archivo de imagen no fue encontrado o es ilegible.', 400);
        }

        $ext = strtolower(pathinfo($localFilePath, PATHINFO_EXTENSION)) ?: 'jpg';
        $s3Key = sprintf('users/%d/avatar_%s.%s', $userId, bin2hex(random_bytes(6)), $ext);

        // Subir a AWS S3
        $s3Url = $this->storageService->upload($localFilePath, $s3Key);

        // Eliminar archivo local temporal tras subida exitosa a AWS S3
        if (file_exists($localFilePath) && is_file($localFilePath)) {
            @unlink($localFilePath);
        }

        // Guardar o actualizar en la tabla polimórfica images
        $imageId = $this->imageRepository->save(
            imageableType: 'user',
            imageableId: $userId,
            type: 'avatar',
            path: $s3Url
        );

        return [
            'image_id'   => $imageId,
            'user_id'    => $userId,
            'avatar_url' => $s3Url,
            'message'    => 'Foto de perfil actualizada exitosamente.'
        ];
    }
}
