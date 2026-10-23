<?php

namespace App\Domain\Towing\ValueObjects;

readonly class RouteSummary
{
    public function __construct(
        public float $distanceKm,
        public float $durationMinutes,
        public ?string $geometryPolyline = null
    ) {}
}