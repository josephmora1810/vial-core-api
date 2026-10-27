<?php

declare(strict_types=1);

namespace App\Domain\Towing\ValueObjects;

use App\Domain\Shared\ValueObjects\Coordinates;
use InvalidArgumentException;

readonly class CoverageZone
{
    public Coordinates $minCoordinates;
    public Coordinates $maxCoordinates;

    /** @var Coordinates[] */
    public array $vertices;

    /**
     * @param Coordinates[] $vertices
     */
    public function __construct(
        public string $code,
        public string $name,
        array $vertices = [],
        ?Coordinates $minCoordinates = null,
        ?Coordinates $maxCoordinates = null
    ) {
        if ($vertices !== []) {
            if (count($vertices) < 3) {
                throw new InvalidArgumentException('Un polígono de cobertura debe tener al menos 3 vértices.');
            }

            $minLat = PHP_FLOAT_MAX;
            $maxLat = -PHP_FLOAT_MAX;
            $minLng = PHP_FLOAT_MAX;
            $maxLng = -PHP_FLOAT_MAX;
            $normalizedVertices = [];

            foreach ($vertices as $vertex) {
                if (!$vertex instanceof Coordinates) {
                    throw new InvalidArgumentException('Todos los vértices de la zona de cobertura deben ser instancias de Coordinates.');
                }
                $normalizedVertices[] = $vertex;
                $minLat = min($minLat, $vertex->getLatitude());
                $maxLat = max($maxLat, $vertex->getLatitude());
                $minLng = min($minLng, $vertex->getLongitude());
                $maxLng = max($maxLng, $vertex->getLongitude());
            }

            $this->vertices = $normalizedVertices;
            $this->minCoordinates = $minCoordinates ?? new Coordinates($minLat, $minLng);
            $this->maxCoordinates = $maxCoordinates ?? new Coordinates($maxLat, $maxLng);
        } elseif ($minCoordinates !== null && $maxCoordinates !== null) {
            if (
                $minCoordinates->getLatitude() > $maxCoordinates->getLatitude() ||
                $minCoordinates->getLongitude() > $maxCoordinates->getLongitude()
            ) {
                throw new InvalidArgumentException('Las coordenadas mínimas deben ser menores o iguales a las coordenadas máximas.');
            }

            $this->minCoordinates = $minCoordinates;
            $this->maxCoordinates = $maxCoordinates;
            $this->vertices = [
                new Coordinates($minCoordinates->getLatitude(), $minCoordinates->getLongitude()),
                new Coordinates($maxCoordinates->getLatitude(), $minCoordinates->getLongitude()),
                new Coordinates($maxCoordinates->getLatitude(), $maxCoordinates->getLongitude()),
                new Coordinates($minCoordinates->getLatitude(), $maxCoordinates->getLongitude()),
            ];
        } else {
            throw new InvalidArgumentException('Debe definir los vértices del polígono (vertices) o los límites (minCoordinates y maxCoordinates).');
        }
    }

    /**
     * @return Coordinates[]
     */
    public function getVertices(): array
    {
        return $this->vertices;
    }

    /**
     * Verifica si una coordenada cae dentro del polígono o los límites de esta zona (Algoritmo Ray-Casting + Bounding Box).
     */
    public function contains(Coordinates $coordinates): bool
    {
        $lat = $coordinates->getLatitude();
        $lng = $coordinates->getLongitude();

        // 1. Pre-filtro rápido por Bounding Box
        if (
            $lat < $this->minCoordinates->getLatitude() ||
            $lat > $this->maxCoordinates->getLatitude() ||
            $lng < $this->minCoordinates->getLongitude() ||
            $lng > $this->maxCoordinates->getLongitude()
        ) {
            return false;
        }

        // 2. Si es un rectángulo simple de 4 vértices alineados al Bounding Box, ya está contenido
        if (count($this->vertices) === 4 && $this->isBoundingBoxRectangle()) {
            return true;
        }

        // 3. Algoritmo Ray-Casting (Point-in-Polygon)
        $inside = false;
        $count = count($this->vertices);
        $j = $count - 1;

        for ($i = 0; $i < $count; $j = $i++) {
            $vi = $this->vertices[$i];
            $vj = $this->vertices[$j];

            if ($vi->equals($coordinates) || $vj->equals($coordinates)) {
                return true;
            }

            $yi = $vi->getLatitude();
            $xi = $vi->getLongitude();
            $yj = $vj->getLatitude();
            $xj = $vj->getLongitude();

            $intersects = (($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi);

            if ($intersects) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    public function equals(self $other): bool
    {
        if ($this->code !== $other->code || $this->name !== $other->name || count($this->vertices) !== count($other->vertices)) {
            return false;
        }

        foreach ($this->vertices as $index => $vertex) {
            if (!$vertex->equals($other->vertices[$index])) {
                return false;
            }
        }

        return true;
    }

    private function isBoundingBoxRectangle(): bool
    {
        return $this->vertices[0]->getLatitude() === $this->minCoordinates->getLatitude()
            && $this->vertices[0]->getLongitude() === $this->minCoordinates->getLongitude()
            && $this->vertices[2]->getLatitude() === $this->maxCoordinates->getLatitude()
            && $this->vertices[2]->getLongitude() === $this->maxCoordinates->getLongitude();
    }
}
