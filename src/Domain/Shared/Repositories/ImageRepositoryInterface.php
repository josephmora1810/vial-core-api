<?php

namespace App\Domain\Shared\Repositories;

interface ImageRepositoryInterface
{
    /**
     * Guarda o asocia una imagen polimórfica a una entidad (documento, usuario/avatar, vehículo, galería).
     *
     * @param string $imageableType Tipo de entidad (ej: 'user', 'document', 'vehicle', 'home_gallery')
     * @param int $imageableId ID de la entidad
     * @param string $type Propósito (ej: 'avatar', 'identity_card', 'gallery_banner')
     * @param string $path URL o ruta del recurso (en AWS S3)
     * @return int ID de la imagen registrada
     */
    public function save(string $imageableType, int $imageableId, string $type, string $path): int;

    /**
     * @return list<array{id: int, imageable_type: string, imageable_id: int, type: string, path: string, created_at: string}>
     */
    public function findByImageable(string $imageableType, int $imageableId, ?string $type = null): array;

    /**
     * @return array{id: int, imageable_type: string, imageable_id: int, type: string, path: string, created_at: string}|null
     */
    public function findLatestByImageable(string $imageableType, int $imageableId, ?string $type = null): ?array;

    public function deleteByImageable(string $imageableType, int $imageableId, ?string $type = null): bool;
}
