<?php

namespace Tests\Unit\Application;

use App\Application\Towing\UseCases\ArriveTowService;
use App\Application\Towing\UseCases\CompleteTowService;
use App\Domain\Contracts\Entities\ServiceContract;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Towing\Entities\TowService;
use App\Domain\Towing\Enums\TowServiceStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryBroadcaster;
use Tests\Doubles\InMemoryServiceContractRepository;
use Tests\Doubles\InMemoryTowServiceRepository;

class ArriveAndCompleteBroadcastTest extends TestCase
{
    private InMemoryTowServiceRepository $towRepo;
    private InMemoryServiceContractRepository $contractRepo;
    private InMemoryBroadcaster $broadcaster;

    private ArriveTowService $arriveUseCase;
    private CompleteTowService $completeUseCase;

    protected function setUp(): void
    {
        $this->towRepo = new InMemoryTowServiceRepository();
        $this->contractRepo = new InMemoryServiceContractRepository();
        $this->broadcaster = new InMemoryBroadcaster();

        $this->arriveUseCase = new ArriveTowService(
            $this->towRepo,
            $this->broadcaster
        );

        $this->completeUseCase = new CompleteTowService(
            $this->towRepo,
            $this->contractRepo,
            $this->broadcaster
        );
    }

    public function testArriveEmitsServiceArrivedWebSocketEventToClientChannel(): void
    {
        $service = TowService::create(
            clientId: 10,
            vehicleId: 20,
            origin: new Coordinates(10.48, -66.90),
            originAddress: 'Av. Principal',
            destination: new Coordinates(10.50, -66.92),
            destinationAddress: 'Taller',
            failureDescription: 'Batería',
            estimatedDistanceKm: 12.0
        );
        $service->accept(driverId: 5);
        $this->towRepo->save($service);

        $result = $this->arriveUseCase->execute($service->getId(), driverId: 5);

        $this->assertSame(TowServiceStatus::InProgress->getValue(), $result['status']);

        // Verificar difusión en WebSocket al canal privado del servicio
        $expectedChannel = "private-tow-{$service->getUuid()->getValue()}";
        $this->assertTrue($this->broadcaster->hasBroadcast('service-arrived', $expectedChannel));

        $broadcasts = $this->broadcaster->getBroadcasts();
        $this->assertNotEmpty($broadcasts);
        $lastBroadcast = end($broadcasts);
        $this->assertSame('service-arrived', $lastBroadcast['event']);
        $this->assertStringContainsString('Tu grúa ha llegado', $lastBroadcast['data']['message']);
    }

    public function testCompleteEmitsServiceCompletedWebSocketEventToClientChannel(): void
    {
        $service = TowService::create(
            clientId: 10,
            vehicleId: 20,
            origin: new Coordinates(10.48, -66.90),
            originAddress: 'Av. Principal',
            destination: new Coordinates(10.50, -66.92),
            destinationAddress: 'Taller',
            failureDescription: 'Batería',
            estimatedDistanceKm: 12.0
        );
        $service->accept(driverId: 5);
        $service->arrive();
        $this->towRepo->save($service);

        $result = $this->completeUseCase->execute($service->getId(), driverId: 5);

        $this->assertSame(TowServiceStatus::Completed->getValue(), $result['status']);

        $expectedChannel = "private-tow-{$service->getUuid()->getValue()}";
        $this->assertTrue($this->broadcaster->hasBroadcast('service-completed', $expectedChannel));

        $broadcasts = $this->broadcaster->getBroadcasts();
        $this->assertNotEmpty($broadcasts);
        $lastBroadcast = end($broadcasts);
        $this->assertSame('service-completed', $lastBroadcast['event']);
        $this->assertTrue($lastBroadcast['data']['can_rate']);
    }
}
