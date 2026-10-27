<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Towing\ValueObjects\CoverageZone;
use App\Infrastructure\Services\Towing\InMemoryCoverageZoneRepository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CoverageZoneTest extends TestCase
{
    public function test_create_coverage_zone_successfully(): void
    {
        $zone = new CoverageZone(
            code: 'CCS',
            name: 'Gran Caracas',
            minCoordinates: new Coordinates(10.15, -67.20),
            maxCoordinates: new Coordinates(10.65, -66.40)
        );
        $this->assertSame('CCS', $zone->code);
        $this->assertSame('Gran Caracas', $zone->name);
        $this->assertCount(4, $zone->getVertices());
    }

    public function test_create_coverage_zone_with_polygon_vertices(): void
    {
        $repo = new InMemoryCoverageZoneRepository();
        $zones = $repo->getActiveZones();

        $this->assertCount(1, $zones);
        $ccs = $zones[0];
        $this->assertSame('CCS', $ccs->code);
        $this->assertSame('Gran Caracas, Litoral Central y Eje Guarenas-Guatire', $ccs->name);
        $this->assertCount(12, $ccs->getVertices());
        $this->assertTrue($ccs->equals($zones[0]));
    }

    public function test_throws_exception_if_polygon_has_fewer_than_three_vertices(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CoverageZone(
            code: 'INVALID',
            name: 'Polígono inválido',
            vertices: [
                new Coordinates(10.50, -66.90),
                new Coordinates(10.51, -66.91),
            ]
        );
    }

    public function test_throws_exception_if_min_coordinates_greater_than_max(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CoverageZone(
            code: 'INVALID',
            name: 'Invalida',
            minCoordinates: new Coordinates(10.80, -66.00),
            maxCoordinates: new Coordinates(10.20, -67.00)
        );
    }

    public function test_contains_returns_true_for_point_inside_caracas(): void
    {
        $repo = new InMemoryCoverageZoneRepository();
        // Coordenada en Plaza Venezuela / Chacao (Caracas)
        $chacao = new Coordinates(10.4910, -66.8530);
        $zone = $repo->findMatchingZone($chacao);
        $this->assertNotNull($zone);
        $this->assertSame('CCS', $zone->code);
        $this->assertTrue($zone->contains($chacao));

        // Coordenada en Guarenas-Guatire
        $guarenas = new Coordinates(10.4700, -66.5400);
        $this->assertNotNull($repo->findMatchingZone($guarenas));

        // Coordenada en La Guaira / Catia La Mar
        $laGuaira = new Coordinates(10.6000, -66.9500);
        $this->assertNotNull($repo->findMatchingZone($laGuaira));
    }

    public function test_find_matching_zone_returns_null_for_point_outside_caracas(): void
    {
        $repo = new InMemoryCoverageZoneRepository();
        // Coordenada en Valencia (fuera de la Gran Caracas)
        $valencia = new Coordinates(10.1800, -68.0000);
        $zone = $repo->findMatchingZone($valencia);
        $this->assertNull($zone);
    }
}
