<?php

namespace Tests\Unit\Domain\Entities;

use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Towing\Entities\TowService;
use App\Domain\Towing\Enums\TowServiceStatus;
use DomainException;
use LogicException;
use PHPUnit\Framework\TestCase;

class TowServiceTest extends TestCase
{
    private Coordinates $origin;
    private Coordinates $destination;

    protected function setUp(): void
    {
        $this->origin = new Coordinates(10.4806, -66.9036); // Caracas
        $this->destination = new Coordinates(10.6000, -66.9500); // Litoral
    }

    private function createPendingService(): TowService
    {
        return TowService::create(
            clientId: 1,
            vehicleId: 10,
            origin: $this->origin,
            originAddress: 'Av. Francisco de Miranda, Caracas',
            destination: $this->destination,
            destinationAddress: 'Taller Autorizado La Guaira',
            failureDescription: 'Falla de transmisión mecánica',
            estimatedDistanceKm: 25.5,
            contractId: 2,
            extraKm: 5.5,
            extraOrderId: null
        );
    }

    public function testCanCreatePendingTowService(): void
    {
        $service = $this->createPendingService();

        $this->assertNull($service->getId());
        $this->assertInstanceOf(Uuid::class, $service->getUuid());
        $this->assertSame(1, $service->getClientId());
        $this->assertSame(10, $service->getVehicleId());
        $this->assertSame(2, $service->getContractId());
        $this->assertNull($service->getDriverId());
        $this->assertSame(TowServiceStatus::Pending, $service->getStatus());
        $this->assertSame(25.5, $service->getEstimatedDistanceKm());
        $this->assertSame(5.5, $service->getExtraKm());
        $this->assertNull($service->getAcceptedAt());
        $this->assertNull($service->getArrivedAt());
        $this->assertNull($service->getCompletedAt());
        $this->assertNull($service->getCancelledAt());
        $this->assertNotNull($service->getCreatedAt());
    }

    public function testDriverCanAcceptPendingService(): void
    {
        $service = $this->createPendingService();
        $service->accept(driverId: 99);

        $this->assertSame(TowServiceStatus::InRoute, $service->getStatus());
        $this->assertSame(99, $service->getDriverId());
        $this->assertNotNull($service->getAcceptedAt());
    }

    public function testCannotAcceptNonPendingService(): void
    {
        $service = $this->createPendingService();
        $service->accept(99);

        $this->expectException(LogicException::class);
        $service->accept(100); // Ya no está en PENDING
    }

    public function testDriverCanMarkArrived(): void
    {
        $service = $this->createPendingService();
        $service->accept(99);
        $service->arrive();

        $this->assertSame(TowServiceStatus::InProgress, $service->getStatus());
        $this->assertNotNull($service->getArrivedAt());
    }

    public function testCannotArriveIfNotInRoute(): void
    {
        $service = $this->createPendingService();

        $this->expectException(LogicException::class);
        $service->arrive(); // Intentar llegar estando en PENDING
    }

    public function testCanCompleteTowService(): void
    {
        $service = $this->createPendingService();
        $service->accept(99);
        $service->arrive();
        $service->complete();

        $this->assertSame(TowServiceStatus::Completed, $service->getStatus());
        $this->assertNotNull($service->getCompletedAt());
    }

    public function testCannotCompleteIfNotInProgress(): void
    {
        $service = $this->createPendingService();
        $service->accept(99);

        $this->expectException(LogicException::class);
        $service->complete(); // Intentar completar sin llegar primero
    }

    public function testClientCanCancelService(): void
    {
        $service = $this->createPendingService();
        $service->cancelByClient('Ya logré encender el vehículo');

        $this->assertSame(TowServiceStatus::CancelledByClient, $service->getStatus());
        $this->assertSame('CLIENT', $service->getCancelledBy());
        $this->assertSame('Ya logré encender el vehículo', $service->getCancellationReason());
        $this->assertNotNull($service->getCancelledAt());
    }

    public function testClientCannotCancelCompletedService(): void
    {
        $service = $this->createPendingService();
        $service->accept(99);
        $service->arrive();
        $service->complete();

        $this->expectException(LogicException::class);
        $service->cancelByClient('Motivo tardío');
    }

    public function testDriverCanCancelAssignedService(): void
    {
        $service = $this->createPendingService();
        $service->accept(99);
        $service->cancelByDriver('Grúa presentó pinchazo de neumático');

        $this->assertSame(TowServiceStatus::CancelledByDriver, $service->getStatus());
        $this->assertSame('DRIVER', $service->getCancelledBy());
        $this->assertSame('Grúa presentó pinchazo de neumático', $service->getCancellationReason());
        $this->assertNotNull($service->getCancelledAt());
    }

    public function testDriverCannotCancelUnassignedService(): void
    {
        $service = $this->createPendingService();

        $this->expectException(DomainException::class);
        $service->cancelByDriver('No puedo tomarlo'); // Está en PENDING
    }

    public function testSystemCanCancelByTimeoutOnlyIfPending(): void
    {
        $service = $this->createPendingService();
        $service->cancelByTimeout();

        $this->assertSame(TowServiceStatus::CancelledByTimeout, $service->getStatus());
        $this->assertSame('SYSTEM_TIMEOUT', $service->getCancelledBy());
        $this->assertNotNull($service->getCancelledAt());
    }

    public function testCannotCancelByTimeoutIfAlreadyAccepted(): void
    {
        $service = $this->createPendingService();
        $service->accept(99);

        $this->expectException(LogicException::class);
        $service->cancelByTimeout(); // Ya fue asignado, no puede vencer por timeout
    }
}
