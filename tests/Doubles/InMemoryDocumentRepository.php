<?php

namespace Tests\Doubles;

use App\Domain\Auth\Entities\Document;
use App\Domain\Auth\Enums\DocumentStatus;
use App\Domain\Auth\Enums\DocumentType;
use App\Domain\Auth\Repositories\DocumentRepositoryInterface;

class InMemoryDocumentRepository implements DocumentRepositoryInterface
{
    /** @var array<int, Document> */
    private array $documents = [];
    private int $nextId = 1;

    public bool $mockIdentityVerified = false;
    public bool $mockVehicleVerified = false;

    public function save(Document $document): void
    {
        if ($document->getId() === null) {
            $document->setId($this->nextId++);
        }
        $this->documents[$document->getId()] = $document;
    }

    public function findById(int $id): ?Document
    {
        return $this->documents[$id] ?? null;
    }

    public function findLatestByEntity(string $documentableType, int $documentableId, DocumentType $documentType): ?Document
    {
        $matches = [];
        foreach ($this->documents as $doc) {
            if ($doc->getDocumentableType() === $documentableType
                && $doc->getDocumentableId() === $documentableId
                && $doc->getDocumentType() === $documentType) {
                $matches[] = $doc;
            }
        }

        if (empty($matches)) {
            return null;
        }

        return end($matches);
    }

    public function findAllByEntity(string $documentableType, int $documentableId): array
    {
        $matches = [];
        foreach ($this->documents as $doc) {
            if ($doc->getDocumentableType() === $documentableType
                && $doc->getDocumentableId() === $documentableId) {
                $matches[] = $doc;
            }
        }
        return array_reverse($matches);
    }

    public function findByStatus(?string $status = 'PENDING'): array
    {
        $matches = [];
        foreach ($this->documents as $doc) {
            if ($status === null || trim($status) === '' || $doc->getStatus()->getValue() === strtoupper(trim($status))) {
                $matches[] = $doc;
            }
        }
        return array_reverse($matches);
    }

    public function isUserIdentityVerified(int $userId): bool
    {
        if ($this->mockIdentityVerified) {
            return true;
        }

        foreach ($this->documents as $doc) {
            if ($doc->getDocumentableType() === 'user'
                && $doc->getDocumentableId() === $userId
                && in_array($doc->getDocumentType(), [DocumentType::IdentityCard, DocumentType::Passport], true)
                && $doc->getStatus() === DocumentStatus::Verified) {
                return true;
            }
        }
        return false;
    }

    public function hasVerifiedVehicleDocument(int $userId): bool
    {
        if ($this->mockVehicleVerified) {
            return true;
        }

        foreach ($this->documents as $doc) {
            if ($doc->getDocumentableType() === 'vehicle'
                && in_array($doc->getDocumentType(), [DocumentType::VehicleRegistration, DocumentType::NivCertificate], true)
                && $doc->getStatus() === DocumentStatus::Verified) {
                return true;
            }
        }
        return false;
    }
}
