<?php

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\Shared\ValueObjects\Coordinates;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CoordinatesTest extends TestCase
{
    public function test_can_create_valid_coordinates(): void
    {
        $coord = new Coordinates(10.4806, -66.9036);

        $this->assertSame(10.4806, $coord->getLatitude());
        $this->assertSame(-66.9036, $coord->getLongitude());
    }

    public function test_equality(): void
    {
        $c1 = new Coordinates(10.4806, -66.9036);
        $c2 = new Coordinates(10.4806, -66.9036);
        $c3 = new Coordinates(10.1234, -66.9036);

        $this->assertTrue($c1->equals($c2));
        $this->assertFalse($c1->equals($c3));
    }

    public function test_throws_exception_if_latitude_out_of_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La latitud debe estar entre -90 y 90 grados.');

        new Coordinates(95.0, 0.0);
    }

    public function test_throws_exception_if_longitude_out_of_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La longitud debe estar entre -180 y 180 grados.');

        new Coordinates(0.0, 185.0);
    }
}
