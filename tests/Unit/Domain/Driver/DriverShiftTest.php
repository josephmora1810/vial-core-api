<?php

namespace Tests\Unit\Domain\Driver;

use App\Domain\Driver\Entities\DriverShift;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class DriverShiftTest extends TestCase
{
    public function testStartShiftEntity(): void
    {
        $shift = DriverShift::start(driverId: 10, craneId: 20);

        $this->assertNull($shift->getId());
        $this->assertSame(10, $shift->getDriverId());
        $this->assertSame(20, $shift->getCraneId());
        $this->assertTrue($shift->isActive());
        $this->assertNull($shift->getEndedAt());
        $this->assertNull($shift->getLastLatitude());
        $this->assertNull($shift->getLastLongitude());
        $this->assertNull($shift->getLastPingAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $shift->getStartedAt());
    }

    public function testEndShiftEntity(): void
    {
        $shift = DriverShift::start(driverId: 10, craneId: 20);
        $this->assertTrue($shift->isActive());

        $shift->end();

        $this->assertFalse($shift->isActive());
        $this->assertNotNull($shift->getEndedAt());
    }

    public function testUpdateCoordinates(): void
    {
        $shift = DriverShift::start(driverId: 10, craneId: 20);
        $shift->updateCoordinates(10.4806, -66.9036);

        $this->assertSame(10.4806, $shift->getLastLatitude());
        $this->assertSame(-66.9036, $shift->getLastLongitude());
        $this->assertNotNull($shift->getLastPingAt());
    }
}
