<?php

namespace App\Application\Users\DTOs;

use App\Domain\Users\Entities\SavedAddress;

class SavedAddressResponseDto
{
    public function __construct(
        public readonly string $id, // UUID v7 público
        public readonly string $label,
        public readonly string $address_line,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly bool $is_favorite,
        public readonly string $created_at
    ) {}

    public static function fromEntity(SavedAddress $address): self
    {
        return new self(
            id: $address->getUuid()->getValue(),
            label: $address->getLabel(),
            address_line: $address->getAddressLine(),
            latitude: $address->getCoordinates()->getLatitude(),
            longitude: $address->getCoordinates()->getLongitude(),
            is_favorite: $address->isFavorite(),
            created_at: $address->getCreatedAt()->format('c')
        );
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'label'        => $this->label,
            'address_line' => $this->address_line,
            'latitude'     => $this->latitude,
            'longitude'    => $this->longitude,
            'is_favorite'  => $this->is_favorite,
            'created_at'   => $this->created_at,
        ];
    }
}
