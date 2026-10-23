<?php

namespace App\Domain\Towing\ValueObjects;

use App\Domain\Shared\ValueObjects\Coordinates;
use InvalidArgumentException;

readonly class CoverageZone
{
    public function __construct(
        public string $code,
        public string $name,
        public Coordinates $minCoordinates,
        public Coordinates $maxCoordinates
    )
    {
        if ($minCoordinates->getLatitude() > $maxCoordinates->getLatitude() || 
            $minCoordinates->getLongitude() > $maxCoordinates->getLongitude()) {
            throw new InvalidArgumentException('Las coordenadas mínimas deben ser menores o iguales a las coordenadas máximas.');
        }
    }

    /**
     * Verifica si una coordenada cae dentro de los límites de ESTA zona.
     */
    public function contains(Coordinates $coordinates): bool
    {
        return $coordinates->getLatitude() >= $this->minCoordinates->getLatitude() &&
            $coordinates->getLatitude() <= $this->maxCoordinates->getLatitude() &&
            $coordinates->getLongitude() >= $this->minCoordinates->getLongitude() &&
            $coordinates->getLongitude() <= $this->maxCoordinates->getLongitude();
    }
}