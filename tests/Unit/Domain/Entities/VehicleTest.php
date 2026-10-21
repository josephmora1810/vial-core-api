<?php

namespace Tests\Unit\Domain\Entities;

use App\Domain\Vehicles\Entities\Vehicle;
use App\Domain\Vehicles\Enums\VehicleStatus;
use App\Domain\Vehicles\ValueObjects\EngineSerial;
use App\Domain\Vehicles\ValueObjects\Niv;
use App\Domain\Vehicles\ValueObjects\PlateNumber;
use App\Domain\Shared\ValueObjects\Uuid;
use PHPUnit\Framework\TestCase;

class VehicleTest extends TestCase
{
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        $this->vehicle = Vehicle::create(
            userId: 1,
            plateNumber: new PlateNumber('AB123CD'),
            niv: new Niv('1HGCR2F83HA123456'),
            engineSerial: new EngineSerial('ENG998877'),
            year: 2018,
            color: 'Plata Metálico',
            brandId: 1,
            modelId: 5,
            brandName: 'Toyota',
            modelName: 'Corolla'
        );
    }

    public function test_creates_vehicle_with_uuid_and_value_objects(): void
    {
        $this->assertInstanceOf(Uuid::class, $this->vehicle->getUuid());
        $this->assertEquals(1, $this->vehicle->getUserId());
        $this->assertEquals('AB123CD', $this->vehicle->getPlateNumber()->getValue());
        $this->assertEquals('1HGCR2F83HA123456', $this->vehicle->getNiv()->getValue());
        $this->assertEquals('ENG998877', $this->vehicle->getEngineSerial()?->getValue());
        $this->assertEquals(2018, $this->vehicle->getYear());
        $this->assertEquals('Plata Metálico', $this->vehicle->getColor());
        $this->assertEquals(VehicleStatus::Pending, $this->vehicle->getStatus());
        $this->assertFalse($this->vehicle->isVerified());
        $this->assertFalse($this->vehicle->isDeleted());
    }

    public function test_verify_updates_status_to_active(): void
    {
        $this->vehicle->verify();
        $this->assertTrue($this->vehicle->isVerified());
        $this->assertEquals(VehicleStatus::Active, $this->vehicle->getStatus());
    }

    public function test_mark_as_occupied_updates_status(): void
    {
        $this->vehicle->markAsOccupied();
        $this->assertEquals(VehicleStatus::Occupied, $this->vehicle->getStatus());
        $this->assertTrue($this->vehicle->getStatus()->isOccupied());
    }

    public function test_soft_delete_sets_deleted_at_and_disabled_status(): void
    {
        $this->vehicle->softDelete();
        $this->assertTrue($this->vehicle->isDeleted());
        $this->assertEquals(VehicleStatus::Disabled, $this->vehicle->getStatus());
        $this->assertNotNull($this->vehicle->getDeletedAt());
    }
}
