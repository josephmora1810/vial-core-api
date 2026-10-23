<?php

namespace Tests\Unit\Application;

use App\Application\Towing\UseCases\QuoteSosAssistance;
use App\Domain\Auth\ValueObjects\Password;
use App\Domain\Contracts\Entities\ServiceContract;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Driver\Entities\DriverShift;
use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Users\Entities\User;
use App\Domain\Users\ValueObjects\BirthDate;
use App\Domain\Users\ValueObjects\Email;
use App\Domain\Users\ValueObjects\Names;
use App\Domain\Users\ValueObjects\NationalId;
use App\Domain\Users\ValueObjects\Phone;
use App\Domain\Users\ValueObjects\Surnames;
use App\Domain\Vehicles\Entities\Vehicle;
use App\Domain\Vehicles\ValueObjects\PlateNumber;
use App\Infrastructure\Services\Towing\InMemoryCoverageZoneRepository;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\FakeExchangeRateService;
use Tests\Doubles\FakeRoutingService;
use Tests\Doubles\InMemoryDriverShiftRepository;
use Tests\Doubles\InMemoryServiceContractRepository;
use Tests\Doubles\InMemoryUserRepository;
use Tests\Doubles\InMemoryVehicleRepository;

class QuoteSosAssistanceTest extends TestCase
{
    private InMemoryCoverageZoneRepository $zoneRepo;
    private InMemoryVehicleRepository $vehicleRepo;
    private FakeRoutingService $routingService;
    private InMemoryServiceContractRepository $contractRepo;
    private InMemoryDriverShiftRepository $shiftRepo;
    private FakeExchangeRateService $exchangeService;

    private QuoteSosAssistance $useCase;
    private User $client;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        $this->zoneRepo = new InMemoryCoverageZoneRepository();
        $this->vehicleRepo = new InMemoryVehicleRepository();
        $this->routingService = new FakeRoutingService(15.0, 20.0);
        $this->contractRepo = new InMemoryServiceContractRepository();
        $this->shiftRepo = new InMemoryDriverShiftRepository();
        $this->exchangeService = new FakeExchangeRateService(50.00);

        $this->useCase = new QuoteSosAssistance(
            $this->zoneRepo,
            $this->vehicleRepo,
            $this->routingService,
            $this->contractRepo,
            $this->shiftRepo,
            $this->exchangeService
        );

        $this->client = new User(
            id: 1,
            uuid: Uuid::generate(),
            names: new Names('Ana'),
            surnames: new Surnames('Perez'),
            email: new Email('ana@example.com'),
            password: Password::fromPlainText('Clave123!'),
            phone: new Phone('+584123334455'),
            walletBalance: new Money(0, 'VES'),
            identificationCard: new NationalId('V-20123456'),
            birthDate: new BirthDate('1992-04-10')
        );

        $this->vehicle = Vehicle::create(
            userId: 1,
            plateNumber: new PlateNumber('AB123CD'),
            niv: null,
            engineSerial: null,
            year: 2018,
            color: 'Rojo',
            modelId: 1
        );
        $this->vehicle->verify();
        $this->vehicleRepo->save($this->vehicle);
    }

    public function testQuoteWhenNoCranesAvailableWarnsUserAndBlocksPayment(): void
    {
        // No hay choferes activos en el shiftRepo
        $origin = new Coordinates(10.4806, -66.9036); // Caracas
        $dest = new Coordinates(10.5000, -66.9200);

        $result = $this->useCase->execute(
            clientId: 1,
            vehicleIdentifier: $this->vehicle->getId(),
            origin: $origin,
            destination: $dest
        );

        $this->assertFalse($result['can_request_service']);
        $this->assertSame(0, $result['nearby_cranes_count']);
        $this->assertNotNull($result['warning_message']);
        $this->assertStringContainsString('no hay grúas operativas disponibles cerca', $result['warning_message']);
    }

    public function testQuoteWhenCranesAvailableAllowsRequestWithPlanCoverage(): void
    {
        // 1. Agregar chofer con turno activo cerca (en radio de 10 km)
        $shift = DriverShift::start(driverId: 99, craneId: 50);
        $shift->updateCoordinates(10.4850, -66.9000); // 0.6 km de distancia
        $this->shiftRepo->save($shift);

        // 2. Cliente con contrato anual activo
        $contract = new ServiceContract(
            planId: 1,
            clientId: 1,
            vehicleId: (int)$this->vehicle->getId(),
            remainingServices: 3,
            remainingKm: 50.0,
            expiresAt: (new DateTimeImmutable())->modify('+1 year'),
            status: ContractStatus::Active,
            id: 1
        );
        $this->contractRepo->save($contract);

        $origin = new Coordinates(10.4806, -66.9036);
        $dest = new Coordinates(10.5000, -66.9200);

        $result = $this->useCase->execute(
            clientId: 1,
            vehicleIdentifier: $this->vehicle->getId(),
            origin: $origin,
            destination: $dest
        );

        $this->assertTrue($result['can_request_service']);
        $this->assertGreaterThan(0, $result['nearby_cranes_count']);
        $this->assertNull($result['warning_message']);

        // Verificamos que esté cubierto por el plan sin costo adicional
        $this->assertTrue($result['pricing']['is_covered_by_plan']);
        $this->assertEquals(0.00, $result['pricing']['amount_usd']);
        $this->assertEquals(0.00, $result['pricing']['amount_ves']);
        $this->assertFalse($result['pricing']['requires_payment']);
        $this->assertEquals(15.0, $result['route']['distance_km']);
    }

    public function testQuoteForExpressWithoutContractCalculatesPrice(): void
    {
        // Chofer activo cerca
        $shift = DriverShift::start(driverId: 99, craneId: 50);
        $shift->updateCoordinates(10.4850, -66.9000);
        $this->shiftRepo->save($shift);

        // Sin contrato activo (Express)
        $origin = new Coordinates(10.4806, -66.9036);
        $dest = new Coordinates(10.5000, -66.9200);

        $result = $this->useCase->execute(
            clientId: 1,
            vehicleIdentifier: $this->vehicle->getId(),
            origin: $origin,
            destination: $dest
        );

        $this->assertTrue($result['can_request_service']);
        $this->assertFalse($result['pricing']['is_covered_by_plan']);
        $this->assertTrue($result['pricing']['requires_payment']);
        // 35 base + 15 km * 2 = 65 USD
        $this->assertEquals(65.00, $result['pricing']['amount_usd']);
        // 65 USD * 50 rate = 3250 VES
        $this->assertEquals(3250.00, $result['pricing']['amount_ves']);
    }

    public function testFailsWhenOriginIsOutsideCoverageGeofence(): void
    {
        // Valencia (fuera de la Gran Caracas / Litoral)
        $origin = new Coordinates(10.1800, -68.0000);
        $dest = new Coordinates(10.2000, -68.0100);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('fuera del área de cobertura operativa');

        $this->useCase->execute(
            clientId: 1,
            vehicleIdentifier: $this->vehicle->getId(),
            origin: $origin,
            destination: $dest
        );
    }
}
