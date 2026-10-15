<?php

namespace App\Domain\Entities;

use App\Domain\ValueObjects\Coordinates;
use App\Domain\ValueObjects\Uuid;
use DateTimeImmutable;

class SavedAddress
{
    public function __construct(
        private ?int $id,
        private Uuid $uuid,
        private int $userId,
        private string $label,
        private string $addressLine,
        private Coordinates $coordinates,
        private bool $isFavorite = false,
        private ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null
    ) {
        $this->createdAt ??= new DateTimeImmutable();
    }

    public static function create(
        int $userId,
        string $label,
        string $addressLine,
        Coordinates $coordinates,
        bool $isFavorite = false,
        ?Uuid $uuid = null
    ): self {
        return new self(
            id: null,
            uuid: $uuid ?? Uuid::generate(),
            userId: $userId,
            label: trim($label),
            addressLine: trim($addressLine),
            coordinates: $coordinates,
            isFavorite: $isFavorite,
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

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = trim($label);
        $this->touch();
        return $this;
    }

    public function getAddressLine(): string
    {
        return $this->addressLine;
    }

    public function setAddressLine(string $addressLine): self
    {
        $this->addressLine = trim($addressLine);
        $this->touch();
        return $this;
    }

    public function getCoordinates(): Coordinates
    {
        return $this->coordinates;
    }

    public function updateCoordinates(Coordinates $coordinates): self
    {
        $this->coordinates = $coordinates;
        $this->touch();
        return $this;
    }

    public function isFavorite(): bool
    {
        return $this->isFavorite;
    }

    public function markAsFavorite(): self
    {
        $this->isFavorite = true;
        $this->touch();
        return $this;
    }

    public function unmarkFavorite(): self
    {
        $this->isFavorite = false;
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
