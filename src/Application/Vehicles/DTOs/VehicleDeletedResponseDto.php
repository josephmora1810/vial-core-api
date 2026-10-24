<?php

declare(strict_types=1);

namespace App\Application\Vehicles\DTOs;

readonly class VehicleDeletedResponseDto
{
    public function __construct(
        public string $id,
        public bool $deleted = true
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'      => $this->id,
            'deleted' => $this->deleted,
        ];
    }
}
