<?php

declare(strict_types=1);

namespace App\Domain\Towing\ValueObjects;

readonly class RouteSummary
{
    public function __construct(
        public float $distanceKm,
        public float $durationMinutes,
        public ?string $geometryPolyline = null
    ) {}

    public function equals(self $other): bool
    {
        return $this->distanceKm === $other->distanceKm
            && $this->durationMinutes === $other->durationMinutes
            && $this->geometryPolyline === $other->geometryPolyline;
    }
}
