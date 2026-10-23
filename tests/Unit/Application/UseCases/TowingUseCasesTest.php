<?php

namespace Tests\Unit\Application\UseCases;

use App\Application\Towing\UseCases\AcceptSosService;
use App\Application\Towing\UseCases\ArriveTowService;
use App\Application\Towing\UseCases\CancelTowService;
use App\Application\Towing\UseCases\CompleteTowService;
use App\Application\Towing\UseCases\ExpireOverdueSosServices;
use App\Application\Towing\UseCases\FindNearbyPendingServices;
use App\Application\Towing\UseCases\GetTowService;
use App\Application\Towing\UseCases\RateTowService;
use App\Application\Towing\UseCases\RequestSosAssistance;
use App\Domain\Contracts\Entities\ServiceContract;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Payments\Entities\Order;
use App\Domain\Payments\Enums\OrderableType;
use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Towing\Entities\TowService;
use App\Domain\Towing\Enums\TowServiceStatus;
use App\Domain\Users\Entities\User;
use App\Domain\Users\ValueObjects\Email;
use App\Domain\Users\ValueObjects\Names;
use App\Domain\Users\ValueObjects\NationalId;
use App\Domain\Users\ValueObjects\Phone;
use App\Domain\Users\ValueObjects\Surnames;
use App\Infrastructure\Services\Towing\InMemoryCoverageZoneRepository;
use App\Domain\Vehicles\Entities\Vehicle;
use App\Domain\Vehicles\Enums\VehicleStatus;
use App\Domain\Vehicles\Enums\VehicleType;
use App\Domain\Vehicles\ValueObjects\Niv;
use App\Domain\Vehicles\ValueObjects\PlateNumber;
use DateTimeImmutable;
use DomainException;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\FakeExchangeRateService;
use Tests\Doubles\FakeRoutingService;
use Tests\Doubles\InMemoryBroadcaster;
use Tests\Doubles\InMemoryOrderRepository;
use Tests\Doubles\InMemoryRatingRepository;
use Tests\Doubles\InMemoryServiceContractRepository;
use Tests\Doubles\InMemoryTowServiceLogRepository;
use Tests\Doubles\InMemoryTowServiceRepository;
use Tests\Doubles\InMemoryUserRepository;
use Tests\Doubles\InMemoryVehicleRepository;

class TowingUseCasesTest extends TestCase
{
    private InMemoryCoverageZoneRepository $zoneRepo;
    private InMemoryVehicleRepository $vehicleRepo;
    private FakeRoutingService $routingService;
    private InMemoryServiceContractRepository $contractRepo;
    private InMemoryTowServiceRepository $towServiceRepo;
    private InMemoryTowServiceLogRepository $logRepo;
    private InMemoryOrderRepository $orderRepo;
    private FakeExchangeRateService $exchangeRateService;
    private InMemoryRatingRepository $ratingRepo;
    private InMemoryBroadcaster $broadcaster;
    private InMemoryUserRepository $userRepo;

    private Vehicle $clientVehicle;
    private int $clientId = 10;
    private int $driverId = 20;

    protected function setUp(): void
    {
        $this->zoneRepo = new InMemoryCoverageZoneRepository();
        $this->vehicleRepo = new InMemoryVehicleRepository();
        $this->routingService = new FakeRoutingService(defaultDistanceKm: 15.0, defaultDurationMinutes: 15.0);
        $this->contractRepo = new InMemoryServiceContractRepository();
        $this->towServiceRepo = new InMemoryTowServiceRepository();
        $this->logRepo = new InMemoryTowServiceLogRepository();
        $this->orderRepo = new InMemoryOrderRepository();
        $this->exchangeRateService = new FakeExchangeRateService(usdRate: 45.0);
        $this->ratingRepo = new InMemoryRatingRepository();
        $this->broadcaster = new InMemoryBroadcaster();
        $this->userRepo = new InMemoryUserRepository();

        // Cliente
        $client = User::create(
            uuid: Uuid::generate(),
            names: new Names('Carlos'),
            surnames: new Surnames('Cliente'),
            email: new Email('carlos@cliente.test'),
            password: null,
            phone: new Phone('+584141112233'),
            identificationCard: new NationalId('V-18999888'),
            walletBalance: new Money(10.00, 'VES')
        );
        $client->setId($this->clientId);
        $this->userRepo->save($client);

        // Vehículo verificado del cliente
        $this->clientVehicle = Vehicle::create(
            userId: $this->clientId,
            plateNumber: new PlateNumber('AB123CD'),
            niv: new Niv('1HGCR2F83HA123456'),
            engineSerial: null,
            year: 2022,
            color: 'Blanco',
            type: VehicleType::Car,
            status: VehicleStatus::Active
        );
        $this->vehicleRepo->save($this->clientVehicle);
    }

    public function testRequestSosFailsWhenOutsideGeofence(): void
    {
        $useCase = new RequestSosAssistance(
            $this->zoneRepo,
            $this->vehicleRepo,
            $this->routingService,
            $this->contractRepo,
            $this->towServiceRepo,
            $this->orderRepo,
            $this->exchangeRateService
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('fuera del área de cobertura');

        // Coordenadas en Maracaibo (fuera de Gran Caracas / Litoral Central)
        $outsideOrigin = new Coordinates(10.6588, -71.6375);
        $dest = new Coordinates(10.5000, -66.9000);

        $useCase->execute(
            clientId: $this->clientId,
            vehicleIdentifier: (int)$this->clientVehicle->getId(),
            origin: $outsideOrigin,
            originAddress: 'Maracaibo Centro',
            destination: $dest,
            destinationAddress: 'Caracas Taller',
            failureDescription: 'Falla de alternador'
        );
    }

    public function testRequestSosFailsWhenVehicleDoesNotBelongToClient(): void
    {
        $useCase = new RequestSosAssistance(
            $this->zoneRepo,
            $this->vehicleRepo,
            $this->routingService,
            $this->contractRepo,
            $this->towServiceRepo,
            $this->orderRepo,
            $this->exchangeRateService
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No tienes autorización para solicitar asistencia para este vehículo');

        $origin = new Coordinates(10.4910, -66.8530);
        $dest = new Coordinates(10.5000, -66.9000);

        $useCase->execute(
            clientId: 999, // Otro cliente
            vehicleIdentifier: (int)$this->clientVehicle->getId(),
            origin: $origin,
            originAddress: 'Altamira, Caracas',
            destination: $dest,
            destinationAddress: 'Chacao Taller',
            failureDescription: 'Falla mecánica'
        );
    }

    public function testRequestSosWithActiveContractNoExcessRequiresNoPayment(): void
    {
        // Contrato con 50 km disponibles
        $contract = new ServiceContract(
            clientId: $this->clientId,
            planId: 1,
            remainingServices: 2,
            remainingKm: 50.0,
            vehicleId: (int)$this->clientVehicle->getId(),
            status: ContractStatus::Active,
            startsAt: new DateTimeImmutable('-1 month'),
            expiresAt: new DateTimeImmutable('+11 months')
        );
        $this->contractRepo->save($contract);

        // Ruta de 15 km (dentro de los 50 km de cobertura)
        $this->routingService->setRoute(distanceKm: 15.0, durationMinutes: 15.0);

        $useCase = new RequestSosAssistance(
            $this->zoneRepo,
            $this->vehicleRepo,
            $this->routingService,
            $this->contractRepo,
            $this->towServiceRepo,
            $this->orderRepo,
            $this->exchangeRateService
        );

        $origin = new Coordinates(10.4910, -66.8530); // Chacao
        $dest = new Coordinates(10.4800, -66.9000);   // Centro

        $result = $useCase->execute(
            clientId: $this->clientId,
            vehicleIdentifier: (int)$this->clientVehicle->getId(),
            origin: $origin,
            originAddress: 'Av. Francisco de Miranda, Chacao',
            destination: $dest,
            destinationAddress: 'Taller Los Caobos',
            failureDescription: 'Caucho espichado y sin gato'
        );

        $this->assertFalse($result['requires_payment']);
        $this->assertNull($result['order']);
        $this->assertSame(15.0, $result['route']['distance_km']);
        $this->assertSame('PENDING', $result['service']['status']);
        $this->assertSame(0.0, $result['service']['extra_km']);
        $this->assertNull($result['service']['extra_order_id']);
    }

    public function testRequestSosWithActiveContractGeneratesOrderWhenExceedingKm(): void
    {
        // Contrato con solo 10 km restantes
        $contract = new ServiceContract(
            clientId: $this->clientId,
            planId: 1,
            remainingServices: 1,
            remainingKm: 10.0,
            vehicleId: (int)$this->clientVehicle->getId(),
            status: ContractStatus::Active,
            startsAt: new DateTimeImmutable('-1 month'),
            expiresAt: new DateTimeImmutable('+11 months')
        );
        $this->contractRepo->save($contract);

        // Ruta de 30 km (excede en 20 km)
        $this->routingService->setRoute(distanceKm: 30.0, durationMinutes: 30.0);

        $useCase = new RequestSosAssistance(
            $this->zoneRepo,
            $this->vehicleRepo,
            $this->routingService,
            $this->contractRepo,
            $this->towServiceRepo,
            $this->orderRepo,
            $this->exchangeRateService,
            costPerExtraKm: 2.0
        );

        $origin = new Coordinates(10.4910, -66.8530);
        $dest = new Coordinates(10.6000, -66.9500);

        $result = $useCase->execute(
            clientId: $this->clientId,
            vehicleIdentifier: (int)$this->clientVehicle->getId(),
            origin: $origin,
            originAddress: 'Caracas Este',
            destination: $dest,
            destinationAddress: 'La Guaira Taller',
            failureDescription: 'Recalentamiento de motor'
        );

        // Exceso: 30 km - 10 km = 20 km -> 20 * $2 = $40 USD
        $this->assertTrue($result['requires_payment']);
        $this->assertNotNull($result['order']);
        $this->assertSame(40.0, $result['order']['amount_usd']);
        $this->assertSame(45.0, $result['order']['exchange_rate']);
        $this->assertSame(1800.0, $result['order']['amount_ves']); // 40 * 45
        $this->assertSame(OrderStatus::Pending->getValue(), $result['order']['status']);
        $this->assertSame(20.0, $result['service']['extra_km']);
        $this->assertNotNull($result['service']['extra_order_id']);
    }

    public function testRequestSosWithoutContractOperatesAsSosExpress(): void
    {
        // Sin contrato registrado para el carro
        $this->routingService->setRoute(distanceKm: 10.0, durationMinutes: 10.0);

        $useCase = new RequestSosAssistance(
            $this->zoneRepo,
            $this->vehicleRepo,
            $this->routingService,
            $this->contractRepo,
            $this->towServiceRepo,
            $this->orderRepo,
            $this->exchangeRateService,
            costPerExtraKm: 2.0,
            baseSosPriceUsd: 35.0
        );

        $origin = new Coordinates(10.4910, -66.8530);
        $dest = new Coordinates(10.5000, -66.9000);

        $result = $useCase->execute(
            clientId: $this->clientId,
            vehicleIdentifier: (int)$this->clientVehicle->getId(),
            origin: $origin,
            originAddress: 'Altamira',
            destination: $dest,
            destinationAddress: 'Bello Monte',
            failureDescription: 'Batería descargada'
        );

        // Tarifa Express: $35 base + (10 km * $2) = $55 USD
        $this->assertTrue($result['requires_payment']);
        $this->assertSame(55.0, $result['order']['amount_usd']);
        $this->assertSame(2475.0, $result['order']['amount_ves']); // 55 * 45
        $this->assertSame(10.0, $result['service']['extra_km']);
    }

    public function testAcceptSosServiceAssignsDriverAndChangesStateToInRoute(): void
    {
        $service = TowService::create(
            clientId: $this->clientId,
            vehicleId: (int)$this->clientVehicle->getId(),
            origin: new Coordinates(10.4910, -66.8530),
            originAddress: 'Chacao',
            destination: new Coordinates(10.5000, -66.9000),
            destinationAddress: 'Caracas Centro',
            failureDescription: 'Avería eléctrica',
            estimatedDistanceKm: 12.0
        );
        $this->towServiceRepo->save($service);

        $useCase = new AcceptSosService($this->towServiceRepo);
        $result = $useCase->execute((int)$service->getId(), $this->driverId);

        $this->assertSame('IN_ROUTE', $result['status']);
        $this->assertNotNull($result['accepted_at']);

        // Verificar persistencia
        $persisted = $this->towServiceRepo->findById((int)$service->getId());
        $this->assertSame(TowServiceStatus::InRoute, $persisted->getStatus());
        $this->assertSame($this->driverId, $persisted->getDriverId());
    }

    public function testAcceptSosFailsIfDriverAlreadyHasActiveJob(): void
    {
        // Crear un primer servicio ya asignado al conductor
        $activeJob = TowService::create(
            clientId: $this->clientId,
            vehicleId: (int)$this->clientVehicle->getId(),
            origin: new Coordinates(10.49, -66.85),
            originAddress: 'A',
            destination: new Coordinates(10.50, -66.90),
            destinationAddress: 'B',
            failureDescription: 'Avería',
            estimatedDistanceKm: 10.0
        );
        $this->towServiceRepo->save($activeJob);
        $activeJob->accept($this->driverId);
        $this->towServiceRepo->save($activeJob);

        // Segundo servicio pendiente
        $pendingJob = TowService::create(
            clientId: $this->clientId,
            vehicleId: (int)$this->clientVehicle->getId(),
            origin: new Coordinates(10.49, -66.85),
            originAddress: 'C',
            destination: new Coordinates(10.50, -66.90),
            destinationAddress: 'D',
            failureDescription: 'Avería 2',
            estimatedDistanceKm: 8.0
        );
        $this->towServiceRepo->save($pendingJob);

        $useCase = new AcceptSosService($this->towServiceRepo);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Ya tienes un servicio de grúa en curso');

        $useCase->execute((int)$pendingJob->getId(), $this->driverId);
    }

    public function testArriveAndCompleteLifecycleDeductsContract(): void
    {
        // Contrato con 2 servicios y 50 km
        $contract = new ServiceContract(
            clientId: $this->clientId,
            planId: 1,
            remainingServices: 2,
            remainingKm: 50.0,
            vehicleId: (int)$this->clientVehicle->getId(),
            status: ContractStatus::Active,
            startsAt: new DateTimeImmutable('-1 month'),
            expiresAt: new DateTimeImmutable('+11 months')
        );
        $this->contractRepo->save($contract);

        $service = TowService::create(
            clientId: $this->clientId,
            vehicleId: (int)$this->clientVehicle->getId(),
            origin: new Coordinates(10.4910, -66.8530),
            originAddress: 'Chacao',
            destination: new Coordinates(10.5000, -66.9000),
            destinationAddress: 'Caracas Centro',
            failureDescription: 'Caja trancada',
            estimatedDistanceKm: 15.0,
            contractId: (int)$contract->getId()
        );
        $this->towServiceRepo->save($service);
        $service->accept($this->driverId);
        $this->towServiceRepo->save($service);

        // 1. Arrive (Llegó al sitio)
        $arriveUseCase = new ArriveTowService($this->towServiceRepo);
        $arriveResult = $arriveUseCase->execute((int)$service->getId(), $this->driverId);
        $this->assertSame('IN_PROGRESS', $arriveResult['status']);

        // 2. Complete (Entregó el carro en el taller)
        $completeUseCase = new CompleteTowService($this->towServiceRepo, $this->contractRepo);
        $completeResult = $completeUseCase->execute((int)$service->getId(), $this->driverId);
        $this->assertSame('COMPLETED', $completeResult['status']);

        // 3. Verificar que el contrato se consumió: 2 - 1 = 1 servicio, 50 - 15 = 35 km
        $updatedContract = $this->contractRepo->findById((int)$contract->getId());
        $this->assertSame(1, $updatedContract->getRemainingServices());
        $this->assertSame(35.0, $updatedContract->getRemainingKm());
    }

    public function testCancelTowServiceByClientAndDriver(): void
    {
        $service = TowService::create(
            clientId: $this->clientId,
            vehicleId: (int)$this->clientVehicle->getId(),
            origin: new Coordinates(10.49, -66.85),
            originAddress: 'Origen',
            destination: new Coordinates(10.50, -66.90),
            destinationAddress: 'Destino',
            failureDescription: 'Falla',
            estimatedDistanceKm: 10.0
        );
        $this->towServiceRepo->save($service);

        $cancelUseCase = new CancelTowService($this->towServiceRepo);

        // Cancelar por cliente
        $result = $cancelUseCase->execute(
            serviceIdentifier: (int)$service->getId(),
            userId: $this->clientId,
            reason: 'Pude encender el carro por mis propios medios',
            actor: 'client'
        );

        $this->assertSame('CANCELLED_BY_CLIENT', $result['status']);
        $this->assertSame('CLIENT', $result['cancelled_by']);
        $this->assertSame('Pude encender el carro por mis propios medios', $result['cancellation_reason']);
    }

    public function testRateTowServiceSavesRatingAndPreventsDuplicates(): void
    {
        $service = TowService::create(
            clientId: $this->clientId,
            vehicleId: (int)$this->clientVehicle->getId(),
            origin: new Coordinates(10.49, -66.85),
            originAddress: 'Origen',
            destination: new Coordinates(10.50, -66.90),
            destinationAddress: 'Destino',
            failureDescription: 'Falla',
            estimatedDistanceKm: 10.0
        );
        $this->towServiceRepo->save($service);
        $service->accept($this->driverId);
        $service->arrive();
        $service->complete();
        $this->towServiceRepo->save($service);

        $rateUseCase = new RateTowService($this->towServiceRepo, $this->ratingRepo);

        // Cliente califica al chofer con 5 estrellas
        $ratingResult = $rateUseCase->execute(
            serviceIdentifier: (int)$service->getId(),
            userId: $this->clientId,
            stars: 5,
            comment: 'Excelente servicio, muy puntual y cuidadoso con el carro'
        );

        $this->assertSame(5, $ratingResult['rating']);
        $this->assertSame('Excelente servicio, muy puntual y cuidadoso con el carro', $ratingResult['comment']);

        // Intentar calificar de nuevo debe fallar
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Ya has emitido una calificación para este servicio');

        $rateUseCase->execute(
            serviceIdentifier: (int)$service->getId(),
            userId: $this->clientId,
            stars: 4
        );
    }

    public function testFindNearbyPendingServicesRadar(): void
    {
        // Servicio pendiente en Chacao
        $pendingChacao = TowService::create(
            clientId: $this->clientId,
            vehicleId: (int)$this->clientVehicle->getId(),
            origin: new Coordinates(10.4910, -66.8530),
            originAddress: 'Chacao',
            destination: new Coordinates(10.50, -66.90),
            destinationAddress: 'Centro',
            failureDescription: 'Falla mecánica',
            estimatedDistanceKm: 8.0
        );
        $this->towServiceRepo->save($pendingChacao);

        // Servicio ya aceptado en Altamira (no debe salir en el radar de pendientes)
        $inRouteAltamira = TowService::create(
            clientId: $this->clientId,
            vehicleId: (int)$this->clientVehicle->getId(),
            origin: new Coordinates(10.4950, -66.8480),
            originAddress: 'Altamira',
            destination: new Coordinates(10.50, -66.90),
            destinationAddress: 'Centro',
            failureDescription: 'Caucho roto',
            estimatedDistanceKm: 6.0
        );
        $this->towServiceRepo->save($inRouteAltamira);
        $inRouteAltamira->accept($this->driverId);
        $this->towServiceRepo->save($inRouteAltamira);

        $radarUseCase = new FindNearbyPendingServices($this->towServiceRepo, $this->vehicleRepo);

        // Chofer ubicado en Las Mercedes
        $driverLocation = new Coordinates(10.4800, -66.8600);
        $results = $radarUseCase->execute($driverLocation, radiusKm: 10.0);

        $this->assertCount(1, $results);
        $this->assertSame('PENDING', $results[0]['status']);
        $this->assertSame('AB123CD', $results[0]['vehicle_plate']);
    }

    public function testUpdateCraneTelemetryAppendsLogPulse(): void
    {
        $service = TowService::create(
            clientId: $this->clientId,
            vehicleId: (int)$this->clientVehicle->getId(),
            origin: new Coordinates(10.49, -66.85),
            originAddress: 'Origen',
            destination: new Coordinates(10.50, -66.90),
            destinationAddress: 'Destino',
            failureDescription: 'Falla eléctrica',
            estimatedDistanceKm: 10.0
        );
        $this->towServiceRepo->save($service);
        $service->accept($this->driverId);
        $this->towServiceRepo->save($service);

        $telemetryUseCase = new \App\Application\Towing\UseCases\UpdateCraneTelemetry(
            $this->towServiceRepo,
            $this->logRepo
        );

        $pulse = new Coordinates(10.4850, -66.8550);
        $result = $telemetryUseCase->execute((int)$service->getId(), $this->driverId, $pulse, speedKmh: 42.5);

        $this->assertSame('logged', $result['status']);
        $this->assertSame(42.5, $result['speed_kmh']);
        $this->assertSame(10.4850, $result['coordinates']['latitude']);

        // Verificar historial en repositorio
        $history = $this->logRepo->getHistoryByServiceId((int)$service->getId());
        $this->assertCount(1, $history);
        $this->assertSame(42.5, $history[0]->getSpeedKmh());
    }

    public function testGetTowServiceReturnsDetails(): void
    {
        $service = TowService::create(
            clientId: $this->clientId,
            vehicleId: (int)$this->clientVehicle->getId(),
            origin: new Coordinates(10.49, -66.85),
            originAddress: 'Origen',
            destination: new Coordinates(10.50, -66.90),
            destinationAddress: 'Destino',
            failureDescription: 'Falla',
            estimatedDistanceKm: 12.0
        );
        $this->towServiceRepo->save($service);

        $getServiceUseCase = new GetTowService($this->towServiceRepo, $this->vehicleRepo);
        $result = $getServiceUseCase->execute((int)$service->getId());

        $this->assertSame($service->getUuid()->getValue(), $result['id']);
        $this->assertSame('AB123CD', $result['vehicle_plate']);
        $this->assertSame('PENDING', $result['status']);
    }

    public function testRealtimeBroadcastingOnSosRequestAcceptAndTelemetry(): void
    {
        $useCase = new RequestSosAssistance(
            $this->zoneRepo,
            $this->vehicleRepo,
            $this->routingService,
            $this->contractRepo,
            $this->towServiceRepo,
            $this->orderRepo,
            $this->exchangeRateService,
            broadcaster: $this->broadcaster
        );

        $origin = new Coordinates(10.4910, -66.8530);
        $dest = new Coordinates(10.5000, -66.9000);

        $result = $useCase->execute(
            clientId: $this->clientId,
            vehicleIdentifier: (int)$this->clientVehicle->getId(),
            origin: $origin,
            originAddress: 'Chacao',
            destination: $dest,
            destinationAddress: 'Caracas Centro',
            failureDescription: 'Falla total'
        );

        // 1. Verificar broadcast de nuevo SOS a las grúas
        $this->assertTrue($this->broadcaster->hasBroadcast('new-sos-request', 'cranes-radar'));
        $serviceUuid = $result['service']['id'];

        // 2. Chofer acepta el servicio
        $acceptUseCase = new AcceptSosService($this->towServiceRepo, $this->broadcaster);
        $acceptUseCase->execute($serviceUuid, $this->driverId);

        // Verificar broadcast de aceptación al canal privado del cliente
        $this->assertTrue($this->broadcaster->hasBroadcast('service-accepted', "tow-channel-{$serviceUuid}"));

        // 3. Chofer emite telemetría
        $telemetryUseCase = new \App\Application\Towing\UseCases\UpdateCraneTelemetry(
            $this->towServiceRepo,
            $this->logRepo,
            $this->broadcaster
        );
        $telemetryUseCase->execute($serviceUuid, $this->driverId, new Coordinates(10.4920, -66.8540), 45.0);

        // Verificar broadcast de actualización de ubicación al canal privado
        $this->assertTrue($this->broadcaster->hasBroadcast('location-updated', "tow-channel-{$serviceUuid}"));
    }

    public function testCancelTowServiceBroadcastsToClientChannel(): void
    {
        $service = TowService::create(
            clientId: $this->clientId,
            vehicleId: (int)$this->clientVehicle->getId(),
            origin: new Coordinates(10.4910, -66.8530),
            originAddress: 'Chacao',
            destination: new Coordinates(10.5000, -66.9000),
            destinationAddress: 'Caracas Centro',
            failureDescription: 'Batería descargada',
            estimatedDistanceKm: 12.0
        );
        $this->towServiceRepo->save($service);

        $cancelUseCase = new CancelTowService($this->towServiceRepo, $this->broadcaster);
        $result = $cancelUseCase->execute(
            serviceIdentifier: $service->getUuid()->getValue(),
            userId: $this->clientId,
            reason: 'Conseguí auxilio particular',
            actor: 'client'
        );

        $this->assertSame('CANCELLED_BY_CLIENT', $result['status']);
        $this->assertTrue($this->broadcaster->hasBroadcast('service-cancelled', "tow-channel-{$service->getUuid()->getValue()}"));
    }

    public function testCancelTowServiceWithDriverReassignmentReopensAndBroadcasts(): void
    {
        $service = TowService::create(
            clientId: $this->clientId,
            vehicleId: (int)$this->clientVehicle->getId(),
            origin: new Coordinates(10.4910, -66.8530),
            originAddress: 'Chacao',
            destination: new Coordinates(10.5000, -66.9000),
            destinationAddress: 'Caracas Centro',
            failureDescription: 'Caucho espichado',
            estimatedDistanceKm: 10.0
        );
        $service->accept($this->driverId);
        $this->towServiceRepo->save($service);

        $cancelUseCase = new CancelTowService($this->towServiceRepo, $this->broadcaster);
        $result = $cancelUseCase->execute(
            serviceIdentifier: $service->getUuid()->getValue(),
            userId: $this->driverId,
            reason: 'Avería mecánica en la grúa',
            actor: 'driver',
            reassign: true
        );

        $this->assertSame('PENDING', $result['status']);
        $updatedService = $this->towServiceRepo->findById($service->getId());
        $this->assertNotNull($updatedService);
        $this->assertNull($updatedService->getDriverId());

        // Verifica que re-emitió la alerta en el radar y avisó al canal privado de la reasignación
        $this->assertTrue($this->broadcaster->hasBroadcast('new-sos-request', 'cranes-radar'));
        $this->assertTrue($this->broadcaster->hasBroadcast('driver-reassigned', "tow-channel-{$service->getUuid()->getValue()}"));
    }

    public function testExpireOverdueSosServicesCancelsOverdueAndRefundsWallet(): void
    {
        // 1. Servicio vencido (hace 15 minutos) con orden de pago express pagada de $35.00
        $order = new Order(
            userId: $this->clientId,
            orderableType: OrderableType::TowService,
            orderableId: 101,
            amountUsd: 35.00,
            exchangeRate: 45.0,
            amountVes: 1575.0,
            status: OrderStatus::Paid
        );
        $order = $this->orderRepo->save($order);

        $overdueService = new TowService(
            id: null,
            uuid: Uuid::generate(),
            clientId: $this->clientId,
            driverId: null,
            vehicleId: (int)$this->clientVehicle->getId(),
            contractId: null,
            origin: new Coordinates(10.4910, -66.8530),
            originAddress: 'Altamira',
            destination: new Coordinates(10.5000, -66.9000),
            destinationAddress: 'El Silencio',
            savedAddressId: null,
            failureDescription: 'Motor fundido',
            estimatedDistanceKm: 15.0,
            extraKm: 15.0,
            extraOrderId: $order->getId(),
            status: TowServiceStatus::Pending,
            cancellationReason: null,
            cancelledBy: null,
            acceptedAt: null,
            arrivedAt: null,
            completedAt: null,
            cancelledAt: null,
            createdAt: (new DateTimeImmutable())->modify('-15 minutes'),
            updatedAt: (new DateTimeImmutable())->modify('-15 minutes')
        );
        $this->towServiceRepo->save($overdueService);

        // 2. Servicio reciente (hace 3 minutos) que NO debe expirar
        $recentService = new TowService(
            id: null,
            uuid: Uuid::generate(),
            clientId: $this->clientId,
            driverId: null,
            vehicleId: (int)$this->clientVehicle->getId(),
            contractId: null,
            origin: new Coordinates(10.4910, -66.8530),
            originAddress: 'Las Mercedes',
            destination: new Coordinates(10.5000, -66.9000),
            destinationAddress: 'Bello Monte',
            savedAddressId: null,
            failureDescription: 'Falla eléctrica',
            estimatedDistanceKm: 5.0,
            extraKm: 0.0,
            extraOrderId: null,
            status: TowServiceStatus::Pending,
            cancellationReason: null,
            cancelledBy: null,
            acceptedAt: null,
            arrivedAt: null,
            completedAt: null,
            cancelledAt: null,
            createdAt: (new DateTimeImmutable())->modify('-3 minutes'),
            updatedAt: (new DateTimeImmutable())->modify('-3 minutes')
        );
        $this->towServiceRepo->save($recentService);

        // 3. Ejecutar caso de uso de expiración de timeouts (tolerancia: 10 min)
        $expireUseCase = new ExpireOverdueSosServices(
            towServiceRepository: $this->towServiceRepo,
            orderRepository: $this->orderRepo,
            userRepository: $this->userRepo,
            contractRepository: $this->contractRepo,
            broadcaster: $this->broadcaster
        );

        $result = $expireUseCase->execute(timeoutMinutes: 10);

        // Aserciones del resultado
        $this->assertSame(1, $result['expired_count']);
        $this->assertSame(1, $result['refunded_count']);

        // Verificar estados en repositorio
        $updatedOverdue = $this->towServiceRepo->findById($overdueService->getId());
        $this->assertNotNull($updatedOverdue);
        $this->assertSame(TowServiceStatus::CancelledByTimeout, $updatedOverdue->getStatus());
        $this->assertSame('SYSTEM_TIMEOUT', $updatedOverdue->getCancelledBy());

        $updatedRecent = $this->towServiceRepo->findById($recentService->getId());
        $this->assertNotNull($updatedRecent);
        $this->assertSame(TowServiceStatus::Pending, $updatedRecent->getStatus());

        // Verificar que el dinero de la orden ($35.00) fue reembolsado al wallet_balance del cliente (10.00 + 35.00 = 45.00)
        $user = $this->userRepo->findById($this->clientId);
        $this->assertNotNull($user);
        $this->assertSame(45.00, $user->getWalletBalance()->getAmount());

        // Verificar broadcasts emitidos
        $this->assertTrue($this->broadcaster->hasBroadcast('service-cancelled', "tow-channel-{$overdueService->getUuid()->getValue()}"));
        $this->assertTrue($this->broadcaster->hasBroadcast('sos-request-expired', 'cranes-radar'));
    }
}
