<?php

namespace Tests\Unit\Application;

use App\Application\Driver\UseCases\GetCurrentDriverShift;
use App\Domain\Driver\Entities\DriverShift;
use App\Domain\Vehicles\Entities\Vehicle;
use App\Domain\Vehicles\ValueObjects\PlateNumber;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\{InMemoryDriverShiftRepository, InMemoryVehicleRepository};

class GetCurrentDriverShiftTest extends TestCase
{
    private InMemoryDriverShiftRepository $shiftRepo;
    private InMemoryVehicleRepository $vehicleRepo;
    private GetCurrentDriverShift $useCase;

    protected function setUp(): void
    {
        $this->shiftRepo = new InMemoryDriverShiftRepository();
        $this->vehicleRepo = new InMemoryVehicleRepository();
        $this->useCase = new GetCurrentDriverShift($this->shiftRepo, $this->vehicleRepo);
    }

    public function testReturnsInactiveWhenNoActiveShift(): void
    {
        $dto = $this->useCase->execute(driverId: 99);

        $this->assertFalse($dto->isActive);
        $this->assertNull($dto->shiftId);
        $this->assertNull($dto->crane);
        $this->assertSame(['is_active' => false, 'shift_id' => null, 'crane_id' => null, 'started_at' => null, 'crane' => null, 'last_location' => null], $dto->toArray());
    }

    public function testReturnsActiveShiftWithCraneAndLocationData(): void
    {
        $crane = Vehicle::create(
            userId: 10,
            plateNumber: new PlateNumber('AB123CD'),
            year: 2021,
            color: 'Blanco',
            modelId: 1
        );
        $crane->setBrandName('Ford');
        $crane->setModelName('F-350');
        $this->vehicleRepo->save($crane);

        $shift = new DriverShift(
            id: 1,
            driverId: 10,
            craneId: $crane->getId(),
            isActive: true,
            startedAt: new DateTimeImmutable('2026-10-23 08:00:00'),
            lastLatitude: 10.4900,
            lastLongitude: -66.8800,
            lastPingAt: new DateTimeImmutable('2026-10-23 08:30:00')
        );
        $this->shiftRepo->save($shift);

        $dto = $this->useCase->execute(driverId: 10);

        $this->assertTrue($dto->isActive);
        $this->assertSame(1, $dto->shiftId);
        $this->assertSame($crane->getId(), $dto->craneId);
        $this->assertNotNull($dto->crane);
        $this->assertSame('Ford', $dto->crane['brand']);
        $this->assertSame('F-350', $dto->crane['model']);
        $this->assertSame('AB123CD', $dto->crane['plate_number']);
        $this->assertSame(10.4900, $dto->lastLocation['latitude']);
    }
}
