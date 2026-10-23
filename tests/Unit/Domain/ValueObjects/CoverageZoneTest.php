<?php

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