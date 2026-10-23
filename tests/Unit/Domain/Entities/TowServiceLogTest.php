<?php

namespace Tests\Unit\Domain\Entities;

use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Towing\Entities\TowServiceLog;
use PHPUnit\Framework\TestCase;

class TowServiceLogTest extends TestCase
{
    public function testCanRecordTelemetryLog(): void
    {
        $coords = new Coordinates(10.4912, -66.8821);
        $log = TowServiceLog::record(
            towServiceId: 10,
            coordinates: $coords,
            speedKmh: 48.5
        );

        $this->assertNull($log->getId());
        $this->assertSame(10, $log->getTowServiceId());
        $this->assertSame($coords, $log->getCoordinates());
        $this->assertSame(48.5, $log->getSpeedKmh());
        $this->assertNotNull($log->getCreatedAt());
    }
}
