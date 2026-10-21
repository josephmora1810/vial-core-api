<?php

namespace App\Domain\Landing\Entities;

use DateTimeImmutable;

class HomeGallery
{
    public function __construct(
        private ?int $id,
        private string $title,
        private ?string $subtitle = null,
        private ?string $description = null,
        private ?string $buttonText = null,
        private ?string $buttonLink = null,
        private int $orderIndex = 0,
        private bool $isActive = true,
        private ?string $imageUrl = null,
        private ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null
    ) {
        $this->createdAt ??= new DateTimeImmutable();
    }

    public static function create(
        string $title,
        ?string $subtitle = null,
        ?string $description = null,
        ?string $buttonText = null,
        ?string $buttonLink = null,
        int $orderIndex = 0,
        bool $isActive = true,
        ?string $imageUrl = null
    ): self {
        return new self(
            id: null,
            title: trim($title),
            subtitle: $subtitle ? trim($subtitle) : null,
            description: $description ? trim($description) : null,
            buttonText: $buttonText ? trim($buttonText) : null,
            buttonLink: $buttonLink ? trim($buttonLink) : null,
            orderIndex: $orderIndex,
            isActive: $isActive,
            imageUrl: $imageUrl,
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);
        $this->touch();
        return $this;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): self
    {
        $this->subtitle = $subtitle ? trim($subtitle) : null;
        $this->touch();
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description ? trim($description) : null;
        $this->touch();
        return $this;
    }

    public function getButtonText(): ?string
    {
        return $this->buttonText;
    }

    public function setButtonText(?string $buttonText): self
    {
        $this->buttonText = $buttonText ? trim($buttonText) : null;
        $this->touch();
        return $this;
    }

    public function getButtonLink(): ?string
    {
        return $this->buttonLink;
    }

    public function setButtonLink(?string $buttonLink): self
    {
        $this->buttonLink = $buttonLink ? trim($buttonLink) : null;
        $this->touch();
        return $this;
    }

    public function getOrderIndex(): int
    {
        return $this->orderIndex;
    }

    public function setOrderIndex(int $orderIndex): self
    {
        $this->orderIndex = $orderIndex;
        $this->touch();
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function activate(): self
    {
        $this->isActive = true;
        $this->touch();
        return $this;
    }

    public function deactivate(): self
    {
        $this->isActive = false;
        $this->touch();
        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;
        $this->touch();
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt ?? new DateTimeImmutable();
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'subtitle'    => $this->subtitle,
            'description' => $this->description,
            'button_text' => $this->buttonText,
            'button_link' => $this->buttonLink,
            'order_index' => $this->orderIndex,
            'is_active'   => $this->isActive,
            'image_url'   => $this->imageUrl,
            'created_at'  => $this->createdAt?->format('c'),
            'updated_at'  => $this->updatedAt?->format('c'),
        ];
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
