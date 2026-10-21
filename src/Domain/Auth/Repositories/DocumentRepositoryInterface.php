<?php

namespace App\Domain\Auth\Repositories;

use App\Domain\Auth\Entities\Document;
use App\Domain\Auth\Enums\DocumentType;

interface DocumentRepositoryInterface
{
    public function save(Document $document): void;

    public function findById(int $id): ?Document;

    public function findLatestByEntity(string $documentableType, int $documentableId, DocumentType $documentType): ?Document;

    /**
     * @return Document[]
     */
    public function findAllByEntity(string $documentableType, int $documentableId): array;

    /**
     * @return Document[]
     */
    public function findByStatus(?string $status = 'PENDING'): array;

    /**
     * Comprueba si el usuario tiene un documento de identidad verificado (cédula o pasaporte).
     */
    public function isUserIdentityVerified(int $userId): bool;

    /**
     * Comprueba si el usuario tiene al menos un vehículo con carnet de circulación o NIV verificado.
     */
    public function hasVerifiedVehicleDocument(int $userId): bool;
}
