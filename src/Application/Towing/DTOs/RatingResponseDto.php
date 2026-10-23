<?php

namespace App\Application\Towing\DTOs;

use App\Domain\Towing\Entities\Rating;

readonly class RatingResponseDto
{
    public function __construct(
        public int $rating,
        public ?string $comment,
        public ?string $created_at = null,
        public ?string $service_id = null
    ) {}

    public static function fromEntity(Rating $rating, ?string $serviceUuid = null): self
    {
        return new self(
            rating: $rating->getRating(),
            comment: $rating->getComment(),
            created_at: $rating->getCreatedAt()?->format('Y-m-d H:i:s'),
            service_id: $serviceUuid
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rating'     => $this->rating,
            'comment'    => $this->comment,
            'created_at' => $this->created_at,
            'service_id' => $this->service_id,
        ];
    }
}
