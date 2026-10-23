<?php

namespace Tests\Unit\Application;

use App\Application\Driver\UseCases\EndDriverShift;
use App\Application\Driver\UseCases\StartDriverShift;
use App\Application\Driver\UseCases\UpdateDriverLocation;
use App\Domain\Auth\Entities\Role;
use App\Domain\Auth\ValueObjects\Password;
use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Towing\Entities\TowService;
use App\Domain\Users\Entities\User;
use App\Domain\Users\Enums\UserRole;
use App\Domain\Users\ValueObjects\BirthDate;
use App\Domain\Users\ValueObjects\Email;
use App\Domain\Users\ValueObjects\Names;
use App\Domain\Users\ValueObjects\NationalId;
use App\Domain\Users\ValueObjects\Phone;
use App\Domain\Users\ValueObjects\Surnames;
use App\Domain\Vehicles\Entities\Vehicle;
use App\Domain\Vehicles\Enums\VehicleStatus;
use App\Domain\Vehicles\Enums\VehicleType;
use App\Domain\Vehicles\ValueObjects\PlateNumber;
use DomainException;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryDriverShiftRepository;
use Tests\Doubles\InMemoryTowServiceRepository;
use Tests\Doubles\InMemoryUserRepository;
use Tests\Doubles\InMemoryVehicleRepository;

class DriverShiftAndCraneActivationTest extends TestCase
{
    private InMemoryDriverShiftRepository $shiftRepo;
    private InMemoryUserRepository $userRepo;
    private InMemoryVehicleRepository $vehicleRepo;
    private InMemoryTowServiceRepository $towRepo;

    private StartDriverShift $startShiftUseCase;
    private EndDriverShift $endShiftUseCase;
    private UpdateDriverLocation $updateLocationUseCase;

    private User $driver;
    private Vehicle $craneA;
    private Vehicle $craneB;

    protected function setUp(): void
    {
        $this->shiftRepo = new InMemoryDriverShiftRepository();
        $this->userRepo = new InMemoryUserRepository();
        $this->vehicleRepo = new InMemoryVehicleRepository();
        $this->towRepo = new InMemoryTowServiceRepository();

        $this->startShiftUseCase = new StartDriverShift(
            $this->shiftRepo,
            $this->userRepo,
            $this->vehicleRepo,
            $this->towRepo
        );

        $this->endShiftUseCase = new EndDriverShift(
            $this->shiftRepo,
            $this->userRepo,
            $this->towRepo
        );

        $this->updateLocationUseCase = new UpdateDriverLocation(
            $this->shiftRepo,
            $this->userRepo
        );

        // Crear conductor
        $this->driver = new User(
            id: 1,
            uuid: Uuid::generate(),
            names: new Names('Juan'),
            surnames: new Surnames('Perez'),
            email: new Email('juan.gruas@example.com'),
            password: Password::fromPlainText('ClaveGruero123!'),
            phone: new Phone('+584140001122'),
            walletBalance: new Money(0, 'VES'),
            identificationCard: new NationalId('V-12345678'),
            birthDate: new BirthDate('1985-05-15'),
            roles: [new Role(1, UserRole::Driver->getValue(), 'api')]
        );
        $this->userRepo->save($this->driver);

        // Grúa A: Ford F-350 (Aprobada y activa)
        $this->craneA = Vehicle::create(
            userId: 1,
            plateNumber: new PlateNumber('AA111AA'),
            niv: null,
            engineSerial: null,
            year: 2020,
            color: 'Blanco',
            modelId: 1,
            type: VehicleType::Crane
        );
        $this->craneA->verify();
        $this->vehicleRepo->save($this->craneA);

        // Grúa B: Chevrolet Kodiak (Aprobada y activa)
        $this->craneB = Vehicle::create(
            userId: 1,
            plateNumber: new PlateNumber('BB222BB'),
            niv: null,
            engineSerial: null,
            year: 2022,
            color: 'Amarillo',
            modelId: 1,
            type: VehicleType::Crane
        );
        $this->craneB->verify();
        $this->vehicleRepo->save($this->craneB);
    }

    public function testDriverCanStartShiftWithSingleActiveCrane(): void
    {
        $result = $this->startShiftUseCase->execute(
            driverUserId: 1,
            craneIdentifier: $this->craneA->getId(),
            initialLatitude: 10.4806,
            initialLongitude: -66.9036
        );

        $this->assertTrue($result['is_on_duty']);
        $this->assertSame('AA111AA', $result['active_crane']['plate_number']);

        $activeShift = $this->shiftRepo->findActiveByDriverId(1);
        $this->assertNotNull($activeShift);
        $this->assertSame($this->craneA->getId(), $activeShift->getCraneId());
    }

    public function testDriverSwitchesCranesGuaranteeingOnlyOneActiveCraneAtATime(): void
    {
        // 1. Inicia con Grúa A
        $this->startShiftUseCase->execute(1, $this->craneA->getId());
        $activeA = $this->shiftRepo->findActiveByDriverId(1);
        $this->assertSame($this->craneA->getId(), $activeA->getCraneId());

        // 2. Cambia a Grúa B
        $result = $this->startShiftUseCase->execute(1, $this->craneB->getId());
        $this->assertSame('BB222BB', $result['active_crane']['plate_number']);

        // 3. Verificar que solo hay 1 turno activo para el chofer y corresponde a la Grúa B
        $activeB = $this->shiftRepo->findActiveByDriverId(1);
        $this->assertSame($this->craneB->getId(), $activeB->getCraneId());

        // Grúa A ya no está activa
        $this->assertNull($this->shiftRepo->findActiveByCraneId($this->craneA->getId()));
    }

    public function testDriverCannotSwitchCranesWhileHavingActiveTowService(): void
    {
        // 1. Inicia con Grúa A
        $this->startShiftUseCase->execute(1, $this->craneA->getId());

        // 2. Simular servicio activo en curso (IN_ROUTE)
        $activeService = TowService::create(
            clientId: 99,
            vehicleId: 10,
            origin: new Coordinates(10.48, -66.90),
            originAddress: 'Av. Casanova',
            destination: new Coordinates(10.50, -66.92),
            destinationAddress: 'Taller',
            failureDescription: 'Caucho espichado',
            estimatedDistanceKm: 5.0
        );
        $activeService->accept(driverId: 1); // Pasa a IN_ROUTE
        $this->towRepo->save($activeService);

        // 3. Intentar cambiar a Grúa B debe ser rechazado con 409
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(409);
        $this->expectExceptionMessage('No puedes cambiar de grúa ni reiniciar turno mientras tienes un servicio de auxilio vial en curso.');

        $this->startShiftUseCase->execute(1, $this->craneB->getId());
    }

    public function testDriverCannotEndShiftWhileHavingActiveTowService(): void
    {
        // 1. Inicia turno
        $this->startShiftUseCase->execute(1, $this->craneA->getId());

        // 2. Asignar servicio activo
        $activeService = TowService::create(
            clientId: 99,
            vehicleId: 10,
            origin: new Coordinates(10.48, -66.90),
            originAddress: 'Av. Casanova',
            destination: new Coordinates(10.50, -66.92),
            destinationAddress: 'Taller',
            failureDescription: 'Falla eléctrica',
            estimatedDistanceKm: 8.0
        );
        $activeService->accept(driverId: 1);
        $this->towRepo->save($activeService);

        // 3. Intentar finalizar turno debe fallar con 409
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(409);
        $this->expectExceptionMessage('No puedes finalizar tu turno mientras tienes un servicio de grúa en curso.');

        $this->endShiftUseCase->execute(1);
    }

    public function testDriverCanEndShiftWhenIdle(): void
    {
        $this->startShiftUseCase->execute(1, $this->craneA->getId());
        $this->assertNotNull($this->shiftRepo->findActiveByDriverId(1));

        $result = $this->endShiftUseCase->execute(1);
        $this->assertFalse($result['is_on_duty']);
        $this->assertNull($this->shiftRepo->findActiveByDriverId(1));
    }

    public function testUpdateDriverLocationHeartbeatUpdatesCoordinates(): void
    {
        $this->startShiftUseCase->execute(1, $this->craneA->getId());

        $coords = new Coordinates(10.4900, -66.8800);
        $result = $this->updateLocationUseCase->execute(1, $coords, 45.5);

        $this->assertSame('success', $result['status']);
        $this->assertEquals(10.4900, $result['coordinates']['latitude']);
        $this->assertEquals(-66.8800, $result['coordinates']['longitude']);
        $this->assertEquals(45.5, $result['speed_kmh']);

        $shift = $this->shiftRepo->findActiveByDriverId(1);
        $this->assertEquals(10.4900, $shift->getLastLatitude());
        $this->assertEquals(-66.8800, $shift->getLastLongitude());
    }
}
