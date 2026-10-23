<?php

namespace Tests\Unit\Domain\Enums;

use App\Domain\Towing\Enums\CancellationReason;
use PHPUnit\Framework\TestCase;

class CancellationReasonTest extends TestCase
{
    public function testForClientReturnsClientReasons(): void
    {
        $reasons = CancellationReason::forClient();

        $this->assertCount(4, $reasons);
        $this->assertSame('VEHICLE_STARTED', $reasons[0]['code']);
        $this->assertSame('El vehículo logró encender / resolví por mi cuenta', $reasons[0]['label']);
        $this->assertFalse($reasons[0]['reassign']);
    }

    public function testForDriverReturnsDriverReasonsWithReassignmentFlags(): void
    {
        $reasons = CancellationReason::forDriver();

        $this->assertCount(4, $reasons);
        $this->assertSame('DRIVER_BREAKDOWN', $reasons[0]['code']);
        $this->assertSame('Avería mecánica en la grúa (Reasignar auxilio)', $reasons[0]['label']);
        $this->assertTrue($reasons[0]['reassign']);
    }

    public function testCatalogReturnsBothClientAndDriver(): void
    {
        $catalog = CancellationReason::catalog();

        $this->assertArrayHasKey('client', $catalog);
        $this->assertArrayHasKey('driver', $catalog);
        $this->assertCount(4, $catalog['client']);
        $this->assertCount(4, $catalog['driver']);
    }
}
