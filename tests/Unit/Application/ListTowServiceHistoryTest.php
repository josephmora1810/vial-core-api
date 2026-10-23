<?php

namespace Tests\Unit\Application;

use App\Application\Towing\UseCases\ListTowServiceHistory;
use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Towing\Entities\TowService;
use App\Domain\Vehicles\Entities\Vehicle;
use App\Domain\Vehicles\ValueObjects\PlateNumber;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryTowServiceRepository;
use Tests\Doubles\InMemoryVehicleRepository;

class ListTowServiceHistoryTest extends TestCase
{
    private InMemoryTowServiceRepository $towRepo;
    private InMemoryVehicleRepository $vehicleRepo;
    private ListTowServiceHistory $useCase;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        $this->towRepo = new InMemoryTowServiceRepository();
        $this->vehicleRepo = new InMemoryVehicleRepository();
        $this->useCase = new ListTowServiceHistory($this->towRepo, $this->vehicleRepo);

        $this->vehicle = Vehicle::create(
            userId: 1,
            plateNumber: new PlateNumber('AB123CD'),
            year: 2019,
            color: 'Azul',
            modelId: 1
        );
        $this->vehicleRepo->save($this->vehicle);
    }

    public function testReturnsEmptyHistoryWhenNoServices(): void
    {
        $result = $this->useCase->execute(userId: 1, role: 'client');

        $this->assertSame([], $result['items']);
        $this->assertSame(0, $result['pagination']['total']);
        $this->assertSame(1, $result['pagination']['page']);
        $this->assertSame(10, $result['pagination']['limit']);
        $this->assertSame(1, $result['pagination']['total_pages']);
    }

    public function testReturnsPaginatedHistoryForClient(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $service = TowService::create(
                clientId: 1,
                vehicleId: $this->vehicle->getId(),
                origin: new Coordinates(10.4800, -66.9000),
                originAddress: "Origen #{$i}",
                destination: new Coordinates(10.5000, -66.9200),
                destinationAddress: "Destino #{$i}",
                failureDescription: "Falla #{$i}",
                estimatedDistanceKm: 10.0 + $i
            );
            $service->setId($i);
            $this->towRepo->save($service);
        }

        // Página 1 con límite 3
        $result = $this->useCase->execute(userId: 1, role: 'client', page: 1, limit: 3);

        $this->assertCount(3, $result['items']);
        $this->assertSame(5, $result['pagination']['total']);
        $this->assertSame(1, $result['pagination']['page']);
        $this->assertSame(3, $result['pagination']['limit']);
        $this->assertSame(2, $result['pagination']['total_pages']);
        $this->assertSame('AB123CD', $result['items'][0]['vehicle_plate']);

        // Página 2 con límite 3
        $resultPage2 = $this->useCase->execute(userId: 1, role: 'client', page: 2, limit: 3);
        $this->assertCount(2, $resultPage2['items']);
    }

    public function testFiltersByStatus(): void
    {
        $service1 = TowService::create(
            clientId: 1,
            vehicleId: $this->vehicle->getId(),
            origin: new Coordinates(10.4800, -66.9000),
            originAddress: "Origen 1",
            destination: new Coordinates(10.5000, -66.9200),
            destinationAddress: "Destino 1",
            failureDescription: "Falla 1",
            estimatedDistanceKm: 10.0
        );
        $service1->setId(1);
        $this->towRepo->save($service1);

        $service2 = TowService::create(
            clientId: 1,
            vehicleId: $this->vehicle->getId(),
            origin: new Coordinates(10.4800, -66.9000),
            originAddress: "Origen 2",
            destination: new Coordinates(10.5000, -66.9200),
            destinationAddress: "Destino 2",
            failureDescription: "Falla 2",
            estimatedDistanceKm: 10.0
        );
        $service2->setId(2);
        $service2->cancelByClient('Ya resolví');
        $this->towRepo->save($service2);

        $resultPending = $this->useCase->execute(userId: 1, role: 'client', status: 'PENDING');
        $this->assertCount(1, $resultPending['items']);
        $this->assertSame('PENDING', $resultPending['items'][0]['status']);

        $resultCancelled = $this->useCase->execute(userId: 1, role: 'client', status: 'CANCELLED_BY_CLIENT');
        $this->assertCount(1, $resultCancelled['items']);
        $this->assertSame('CANCELLED_BY_CLIENT', $resultCancelled['items'][0]['status']);
    }
}
