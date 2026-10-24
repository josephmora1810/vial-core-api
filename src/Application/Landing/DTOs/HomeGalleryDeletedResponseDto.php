<?php

namespace App\Application\Landing\DTOs;

readonly class HomeGalleryDeletedResponseDto
{
    public function __construct(
        public bool $deleted,
        public int $id
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            deleted: (bool)($data['deleted'] ?? false),
            id: (int)($data['id'] ?? 0)
        );
    }

    /**
     * @return array{deleted: bool, id: int}
     */
    public function toArray(): array
    {
        return [
            'deleted' => $this->deleted,
            'id'      => $this->id,
        ];
    }
}
