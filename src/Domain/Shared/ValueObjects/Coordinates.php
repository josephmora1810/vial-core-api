<?php

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

readonly class Coordinates
{
    public function __construct(
        private float $latitude,
        private float $longitude
    ) {
        $this->ensureAreValidCoordinates($latitude, $longitude);
    }

    private function ensureAreValidCoordinates(float $lat, float $lng): void
    {
        if ($lat < -90 || $lat > 90) {
            throw new InvalidArgumentException('La latitud debe estar entre -90 y 90 grados.');
        }
        if ($lng < -180 || $lng > 180) {
            throw new InvalidArgumentException('La longitud debe estar entre -180 y 180 grados.');
        }
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function equals(Coordinates $other): bool
    {
        return $this->latitude === $other->getLatitude() 
            && $this->longitude === $other->getLongitude();
    }
}