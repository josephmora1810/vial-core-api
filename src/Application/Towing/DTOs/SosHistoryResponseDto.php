<?php

declare(strict_types=1);

namespace App\Application\Towing\DTOs;

readonly class SosHistoryResponseDto
{
    /**
     * @param list<mixed> $items
     * @param array{total: int, page: int, limit: int, total_pages: int} $pagination
     */
    public function __construct(
        public array $items,
        public array $pagination
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            items: (array)($data['items'] ?? []),
            pagination: (array)($data['pagination'] ?? [
                'total'       => 0,
                'page'        => 1,
                'limit'       => 10,
                'total_pages' => 0,
            ])
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items'      => $this->items,
            'pagination' => $this->pagination,
        ];
    }
}
