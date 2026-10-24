<?php

namespace App\Application\Landing\DTOs;

use App\Domain\Landing\Entities\HomeGallery;

readonly class HomeGalleryResponseDto
{
    public function __construct(
        public ?int $id,
        public string $title,
        public ?string $subtitle,
        public ?string $description,
        public ?string $button_text,
        public ?string $button_link,
        public int $order_index,
        public bool $is_active,
        public ?string $image_url,
        public ?string $created_at,
        public ?string $updated_at
    ) {}

    public static function fromEntity(HomeGallery $item): self
    {
        return self::fromArray($item->toArray());
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            title: (string)($data['title'] ?? ''),
            subtitle: self::nullableString($data['subtitle'] ?? null),
            description: self::nullableString($data['description'] ?? null),
            button_text: self::nullableString($data['button_text'] ?? null),
            button_link: self::nullableString($data['button_link'] ?? null),
            order_index: (int)($data['order_index'] ?? 0),
            is_active: (bool)($data['is_active'] ?? true),
            image_url: self::nullableString($data['image_url'] ?? null),
            created_at: self::nullableString($data['created_at'] ?? null),
            updated_at: self::nullableString($data['updated_at'] ?? null)
        );
    }

    /**
     * @param list<HomeGallery> $items
     * @return list<array<string, mixed>>
     */
    public static function collectionToArray(array $items): array
    {
        return array_values(array_map(
            static fn(HomeGallery $item): array => self::fromEntity($item)->toArray(),
            $items
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'subtitle'    => $this->subtitle,
            'description' => $this->description,
            'button_text' => $this->button_text,
            'button_link' => $this->button_link,
            'order_index' => $this->order_index,
            'is_active'   => $this->is_active,
            'image_url'   => $this->image_url,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string)$value;
    }
}
