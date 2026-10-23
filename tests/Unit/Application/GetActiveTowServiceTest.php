<?php

namespace Tests\Unit\Application;

use App\Application\Towing\UseCases\GetActiveTowService;
use App\Application\Towing\UseCases\GetTowService;
use App\Domain\Auth\ValueObjects\Password;
use App\Domain\Driver\Entities\DriverShift;
use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Towing\Entities\TowService;
use App\Domain\Users\Entities\User;
use App\Domain\Users\ValueObjects\BirthDate;
use App\Domain\Users\ValueObjects\Email;
use App\Domain\Users\ValueObjects\Names;
use App\Domain\Users\ValueObjects\NationalId;
use App\Domain\Users\ValueObjects\Phone;
use App\Domain\Users\ValueObjects\Surnames;
use App\Domain\Vehicles\Entities\Vehicle;
use App\Domain\Vehicles\ValueObjects\PlateNumber;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryDriverShiftRepository;
use Tests\Doubles\InMemoryRatingRepository;
use Tests\Doubles\InMemoryTowServiceRepository;
use Tests\Doubles\InMemoryUserRepository;
use Tests\Doubles\InMemoryVehicleRepository;

class GetActiveTowServiceTest extends TestCase
{
    private InMemoryTowServiceRepository $towRepo;
    private InMemoryVehicleRepository $vehicleRepo;
    private InMemoryUserRepository $userRepo;
    private InMemoryDriverShiftRepository $shiftRepo;
    private InMemoryRatingRepository $ratingRepo;

    private GetTowService $getTowService;
    private GetActiveTowService $getActiveTowService;

    private User $client;
    private User $driver;
    private Vehicle $clientCar;
    private Vehicle $crane;

    protected function setUp(): void
    {
        $this->towRepo = new InMemoryTowServiceRepository();
        $this->vehicleRepo = new InMemoryVehicleRepository();
        $this->userRepo = new InMemoryUserRepository();
        $this->shiftRepo = new InMemoryDriverShiftRepository();
        $this->ratingRepo = new InMemoryRatingRepository();

        $this->getTowService = new GetTowService(
            $this->towRepo,
            $this->vehicleRepo,
            $this->userRepo,
            $this->shiftRepo,
            $this->ratingRepo
        );

        $this->getActiveTowService = new GetActiveTowService($this->towRepo, $this->getTowService);

        // Crear Cliente
        $this->client = new User(
            id: 1,
            uuid: Uuid::generate(),
            names: new Names('Carlos'),
            surnames: new Surnames('Gomez'),
            email: new Email('carlos@example.com'),
            password: Password::fromPlainText('Clave123!'),
            phone: new Phone('+584141112233'),
            walletBalance: new Money(0, 'VES'),
            identificationCard: new NationalId('V-18999888'),
            birthDate: new BirthDate('1990-01-01')
        );
        $this->userRepo->save($this->client);

        // Crear Conductor
        $this->driver = new User(
            id: 5,
            uuid: Uuid::generate(),
            names: new Names('Juan'),
            surnames: new Surnames('Perez'),
            email: new Email('juan@gruas.com'),
            password: Password::fromPlainText('Gruero123!'),
            phone: new Phone('+584125556677'),
            walletBalance: new Money(0, 'VES'),
            identificationCard: new NationalId('V-15666777'),
            birthDate: new BirthDate('1985-05-15')
        );
        $this->userRepo->save($this->driver);

        // Vehículo del cliente
        $this->clientCar = Vehicle::create(
            userId: 1,
            plateNumber: new PlateNumber('AB123CD'),
            year: 2018,
            color: 'Rojo',
            modelId: 1
        );
        $this->clientCar->setBrandName('Toyota');
        $this->clientCar->setModelName('Corolla');
        $this->vehicleRepo->save($this->clientCar);

        // Grúa
        $this->crane = Vehicle::create(
            userId: 5,
            plateNumber: new PlateNumber('CR456XY'),
            year: 2020,
            color: 'Blanco',
            modelId: 2
        );
        $this->crane->setBrandName('Ford');
        $this->crane->setModelName('F-350');
        $this->vehicleRepo->save($this->crane);

        // Turno del chofer con la grúa
        $shift = new DriverShift(
            id: 1,
            driverId: 5,
            craneId: $this->crane->getId(),
            isActive: true,
            startedAt: new DateTimeImmutable('now')
        );
        $this->shiftRepo->save($shift);
    }

    public function testReturnsNullWhenUserHasNoActiveService(): void
    {
        $result = $this->getActiveTowService->execute(userId: 1, role: 'client');
        $this->assertNull($result);
    }

    public function testReturnsEnrichedDtoWhenClientHasActiveService(): void
    {
        $service = TowService::create(
            clientId: 1,
            vehicleId: $this->clientCar->getId(),
            origin: new Coordinates(10.4800, -66.9000),
            originAddress: 'Av. Francisco de Miranda',
            destination: new Coordinates(10.5000, -66.9200),
            destinationAddress: 'Taller AutoFix',
            failureDescription: 'Caucho espichado',
            estimatedDistanceKm: 12.5
        );
        $service->accept(driverId: 5);
        $this->towRepo->save($service);

        $result = $this->getActiveTowService->execute(userId: 1, role: 'client');

        $this->assertNotNull($result);
        $this->assertSame('IN_ROUTE', $result['status']);
        $this->assertSame('AB123CD', $result['vehicle_plate']);

        // Verificar datos anidados del chofer
        $this->assertNotNull($result['driver']);
        $this->assertSame('Juan Perez', $result['driver']['name']);
        $this->assertSame('+584125556677', $result['driver']['phone']);
        $this->assertSame('JP', $result['driver']['initials']);

        // Verificar datos de la grúa
        $this->assertNotNull($result['crane']);
        $this->assertSame('Ford', $result['crane']['brand']);
        $this->assertSame('F-350', $result['crane']['model']);
        $this->assertSame('CR456XY', $result['crane']['plate_number']);

        // Verificar datos del vehículo del cliente
        $this->assertNotNull($result['vehicle']);
        $this->assertSame('Toyota', $result['vehicle']['brand']);
        $this->assertSame('Corolla', $result['vehicle']['model']);
        $this->assertSame('Rojo', $result['vehicle']['color']);
    }

    public function testReturnsEnrichedDtoWhenDriverChecksActiveService(): void
    {
        $service = TowService::create(
            clientId: 1,
            vehicleId: $this->clientCar->getId(),
            origin: new Coordinates(10.4800, -66.9000),
            originAddress: 'Av. Francisco de Miranda',
            destination: new Coordinates(10.5000, -66.9200),
            destinationAddress: 'Taller AutoFix',
            failureDescription: 'Falla eléctrica',
            estimatedDistanceKm: 8.0
        );
        $service->accept(driverId: 5);
        $this->towRepo->save($service);

        $result = $this->getActiveTowService->execute(userId: 5, role: 'driver');

        $this->assertNotNull($result);
        $this->assertSame('IN_ROUTE', $result['status']);

        // El chofer ve los datos del cliente
        $this->assertNotNull($result['client']);
        $this->assertSame('Carlos Gomez', $result['client']['name']);
        $this->assertSame('+584141112233', $result['client']['phone']);
        $this->assertSame('CG', $result['client']['initials']);
    }
}
