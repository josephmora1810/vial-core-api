<?php

namespace Tests\Doubles;

use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Towing\Service\RoutingServiceInterface;
use App\Domain\Towing\ValueObjects\RouteSummary;

class FakeRoutingService implements RoutingServiceInterface
{
    public function __construct(
        private float $defaultDistanceKm = 15.0,
        private float $defaultDurationMinutes = 20.0
    ) {}

    public function setRoute(float $distanceKm, float $durationMinutes = 20.0): void
    {
        $this->defaultDistanceKm = $distanceKm;
        $this->defaultDurationMinutes = $durationMinutes;
    }

    public function calculateRoute(Coordinates $origin, Coordinates $destination): RouteSummary
    {
        return new RouteSummary(
            distanceKm: $this->defaultDistanceKm,
            durationMinutes: $this->defaultDurationMinutes,
            geometryPolyline: null
        );
    }
}
