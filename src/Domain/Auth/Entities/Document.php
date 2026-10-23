<?php

namespace App\Domain\Auth\Entities;

use App\Domain\Auth\Enums\{DocumentStatus, DocumentType};
use DateTimeImmutable;

class Document
{
    public function __construct(
        private ?int $id,
        private string $documentableType,
        private int $documentableId,
        private DocumentType $documentType,
        private string $filePath,
        private ?array $extractedData = null,
        private DocumentStatus $status = DocumentStatus::Pending,
        private ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null
    ) {
        $this->createdAt ??= new DateTimeImmutable();
    }

    public static function create(
        string $documentableType,
        int $documentableId,
        DocumentType $documentType,
        string $filePath,
        ?array $extractedData = null,
        DocumentStatus $status = DocumentStatus::Pending
    ): self {
        return new self(
            id: null,
            documentableType: $documentableType,
            documentableId: $documentableId,
            documentType: $documentType,
            filePath: $filePath,
            extractedData: $extractedData,
            status: $status,
            createdAt: new DateTimeImmutable()
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getDocumentableType(): string
    {
        return $this->documentableType;
    }

    public function getDocumentableId(): int
    {
        return $this->documentableId;
    }

    public function getDocumentType(): DocumentType
    {
        return $this->documentType;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): self
    {
        $this->filePath = $filePath;
        $this->touch();
        return $this;
    }

    public function getExtractedData(): ?array
    {
        return $this->extractedData;
    }

    public function setExtractedData(?array $extractedData): self
    {
        $this->extractedData = $extractedData;
        $this->touch();
        return $this;
    }

    public function getStatus(): DocumentStatus
    {
        return $this->status;
    }

    public function isVerified(): bool
    {
        return $this->status === DocumentStatus::Verified;
    }

    public function isPending(): bool
    {
        return $this->status === DocumentStatus::Pending;
    }

    public function isRejected(): bool
    {
        return $this->status === DocumentStatus::Rejected;
    }

    public function verify(): self
    {
        $this->status = DocumentStatus::Verified;
        $this->touch();
        return $this;
    }

    public function reject(): self
    {
        $this->status = DocumentStatus::Rejected;
        $this->touch();
        return $this;
    }

    public function resubmit(string $newFilePath, ?array $extractedData = null): self
    {
        $this->filePath = $newFilePath;
        $this->extractedData = $extractedData;
        $this->status = DocumentStatus::Pending;
        $this->touch();
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
