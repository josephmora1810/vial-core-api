<?php

namespace Tests\Unit\Controllers;

use App\Application\Driver\UseCases\{
    EndDriverShift,
    GetCurrentDriverShift,
    GetDriverFleetStatus,
    RegisterDriverCrane,
    StartDriverShift,
    UpdateDriverLocation,
    UploadDriverLicense
};
use App\Controllers\DriverController;
use App\Core\{Request, Response};
use App\Domain\Auth\{Entities\Role, ValueObjects\Password};
use App\Domain\Driver\Repositories\DriverShiftRepositoryInterface;
use App\Domain\Shared\ValueObjects\{Money, Uuid};
use App\Domain\Users\Entities\User;
use App\Domain\Users\ValueObjects\{
    BirthDate,
    Email,
    Names,
    NationalId,
    Phone,
    Surnames
};
use App\Domain\Vehicles\Entities\Vehicle;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use App\Domain\Vehicles\ValueObjects\PlateNumber;
use DateTimeImmutable;
use DomainException;
use Exception;
use PHPUnit\Framework\TestCase;

class DriverControllerTest extends TestCase
{
    private RegisterDriverCrane $registerDriverCrane;
    private UploadDriverLicense $uploadDriverLicense;
    private GetDriverFleetStatus $getDriverFleetStatus;
    private VehicleRepositoryInterface $vehicleRepo;
    private StartDriverShift $startDriverShift;
    private EndDriverShift $endDriverShift;
    private UpdateDriverLocation $updateDriverLocation;
    private DriverShiftRepositoryInterface $driverShiftRepo;
    private GetCurrentDriverShift $getCurrentDriverShift;
    private DriverController $controller;

    protected function setUp(): void
    {
        Response::$shouldExit = false;
        $_POST = [];
        $_GET = [];
        $_FILES = [];

        $this->registerDriverCrane = $this->createStub(RegisterDriverCrane::class);
        $this->uploadDriverLicense = $this->createStub(UploadDriverLicense::class);
        $this->getDriverFleetStatus = $this->createStub(GetDriverFleetStatus::class);
        $this->vehicleRepo = $this->createStub(VehicleRepositoryInterface::class);
        $this->startDriverShift = $this->createStub(StartDriverShift::class);
        $this->endDriverShift = $this->createStub(EndDriverShift::class);
        $this->updateDriverLocation = $this->createStub(UpdateDriverLocation::class);
        $this->driverShiftRepo = $this->createStub(DriverShiftRepositoryInterface::class);
        $this->getCurrentDriverShift = $this->createStub(GetCurrentDriverShift::class);

        $this->controller = new DriverController(
            registerDriverCrane: $this->registerDriverCrane,
            uploadDriverLicense: $this->uploadDriverLicense,
            getDriverFleetStatus: $this->getDriverFleetStatus,
            vehicleRepository: $this->vehicleRepo,
            startDriverShift: $this->startDriverShift,
            endDriverShift: $this->endDriverShift,
            updateDriverLocation: $this->updateDriverLocation,
            driverShiftRepository: $this->driverShiftRepo,
            getCurrentDriverShift: $this->getCurrentDriverShift
        );
    }

    protected function tearDown(): void
    {
        Response::$shouldExit = true;
        $_POST = [];
        $_GET = [];
        $_FILES = [];
    }

    private function createDriverUser(int $id = 5): User
    {
        return new User(
            id: $id,
            uuid: Uuid::generate(),
            names: new Names('Jose'),
            surnames: new Surnames('Gruero'),
            email: new Email('gruero@example.com'),
            password: Password::fromPlainText('DriverSecret123!'),
            phone: new Phone('+584149998877'),
            walletBalance: new Money(0, 'VES'),
            identificationCard: new NationalId('V-18765432'),
            birthDate: new BirthDate('1985-05-10'),
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            consentAt: new DateTimeImmutable(),
            deletedAt: null,
            isActive: true,
            roles: [new Role(2, 'driver')]
        );
    }

    private function capture(callable $callback): array
    {
        ob_start();
        $callback();
        $output = (string)ob_get_clean();
        return json_decode($output, true) ?: [];
    }

    public function testRegisterCraneUnauthorized(): void
    {
        $res = $this->capture(fn() => $this->controller->registerCrane(new Request()));
        $this->assertSame(401, $res['code']);
        $this->assertSame('No autorizado.', $res['message']);
    }

    public function testRegisterCraneSuccess(): void
    {
        $user = $this->createDriverUser();
        $_POST = ['plate_number' => 'AB123CD', 'niv' => '8Z1ABCDE123456789'];
        $request = new Request();
        $request->setUser($user);

        $this->registerDriverCrane->method('execute')->willReturn([
            'crane' => ['id' => 1, 'plate_number' => 'AB123CD'],
            'carnet_document_id' => 10,
            'license_document_id' => 20,
            'is_fleet_ready' => true,
            'status_message' => 'Grúa registrada con éxito en el sistema.',
        ]);

        $res = $this->capture(fn() => $this->controller->registerCrane($request));
        $this->assertSame(201, $res['code']);
        $this->assertSame('success', $res['status']);
        $this->assertTrue($res['data']['is_fleet_ready']);
    }

    public function testUploadLicenseMissingFileReturns422(): void
    {
        $user = $this->createDriverUser();
        $request = new Request();
        $request->setUser($user);

        $res = $this->capture(fn() => $this->controller->uploadLicense($request));
        $this->assertSame(422, $res['code']);
        $this->assertArrayHasKey('license', $res['data']);
    }

    public function testUploadLicenseSuccess(): void
    {
        $user = $this->createDriverUser();
        $tmp = tempnam(sys_get_temp_dir(), 'lic_');
        file_put_contents($tmp, 'dummy-license-content');

        $_POST = ['license_file_path' => $tmp, 'file_path' => $tmp, 'grade' => '5TO GRADO'];
        $request = new Request();
        $request->setUser($user);

        $this->uploadDriverLicense->method('execute')->willReturn([
            'id' => 15,
            'document_type' => 'driving_license',
            'file_path' => $tmp,
            'status' => 'PENDING',
            'extracted_data' => ['grade' => '5TO GRADO'],
            'message' => 'Licencia de conducir cargada exitosamente.',
        ]);

        $res = $this->capture(fn() => $this->controller->uploadLicense($request));
        $this->assertSame(201, $res['code']);
        $this->assertSame('driving_license', $res['data']['document_type']);

        @unlink($tmp);
    }

    public function testStatusSuccess(): void
    {
        $user = $this->createDriverUser();
        $request = new Request();
        $request->setUser($user);

        $this->getDriverFleetStatus->method('execute')->willReturn([
            'driver_id' => 5,
            'driver_name' => 'Jose Gruero',
            'can_operate' => true,
            'is_fleet_ready' => true,
            'identity_card_status' => 'VERIFIED',
            'driving_license_status' => 'VERIFIED',
            'cranes_count' => 1,
            'cranes' => [],
            'pending_requirements' => [],
            'status_message' => 'Operativo.',
        ]);

        $res = $this->capture(fn() => $this->controller->status($request));
        $this->assertSame(200, $res['code']);
        $this->assertTrue($res['data']['can_operate']);
    }

    public function testListCranesWithoutRepoReturns500(): void
    {
        $ctrl = new DriverController(
            $this->registerDriverCrane,
            $this->uploadDriverLicense,
            $this->getDriverFleetStatus,
            vehicleRepository: null
        );
        $user = $this->createDriverUser();
        $request = new Request();
        $request->setUser($user);
        $res = $this->capture(fn() => $ctrl->listCranes($request));
        $this->assertSame(500, $res['code']);
    }

    public function testListCranesSuccess(): void
    {
        $user = $this->createDriverUser();
        $vehicle = new Vehicle(
            id: 10,
            uuid: Uuid::generate(),
            userId: 5,
            plateNumber: new PlateNumber('AB123CD'),
            brandName: 'Ford',
            modelName: 'F-350',
            color: 'Blanco',
            year: 2021
        );
        $this->vehicleRepo->method('findCranesByUserId')->willReturn([$vehicle]);

        $request = new Request();
        $request->setUser($user);
        $res = $this->capture(fn() => $this->controller->listCranes($request));
        $this->assertSame(200, $res['code']);
        $this->assertCount(1, $res['data']);
        $this->assertSame('Ford', $res['data'][0]['brand']);
    }

    public function testStartShiftWithoutCraneIdReturns422(): void
    {
        $user = $this->createDriverUser();
        $request = new Request();
        $request->setUser($user);
        $res = $this->capture(fn() => $this->controller->startShift($request));
        $this->assertSame(422, $res['code']);
    }

    public function testStartShiftSuccess(): void
    {
        $user = $this->createDriverUser();
        $_POST = ['crane_id' => 1, 'latitude' => 10.48, 'longitude' => -66.90];
        $request = new Request();
        $request->setUser($user);

        $this->startDriverShift->method('execute')->willReturn([
            'shift_id' => 50,
            'driver_id' => 5,
            'is_on_duty' => true,
            'message' => 'Turno iniciado con éxito.',
        ]);

        $res = $this->capture(fn() => $this->controller->startShift($request));
        $this->assertSame(200, $res['code']);
        $this->assertTrue($res['data']['is_on_duty']);
    }

    public function testStartShiftDomainExceptionReturns422(): void
    {
        $user = $this->createDriverUser();
        $_POST = ['crane_id' => 1];
        $request = new Request();
        $request->setUser($user);
        $this->startDriverShift->method('execute')->willThrowException(new DomainException('Ya tienes un turno activo.', 422));

        $res = $this->capture(fn() => $this->controller->startShift($request));
        $this->assertSame(422, $res['code']);
    }

    public function testActivateCraneSuccess(): void
    {
        $user = $this->createDriverUser();
        $request = new Request();
        $request->setUser($user);
        $this->startDriverShift->method('execute')->willReturn([
            'shift_id' => 51,
            'driver_id' => 5,
            'is_on_duty' => true,
            'message' => 'Grúa activada como unidad de guardia para el chofer.',
        ]);

        $res = $this->capture(fn() => $this->controller->activateCrane($request, ['id' => 2]));
        $this->assertSame(200, $res['code']);
        $this->assertTrue($res['data']['is_on_duty']);
    }

    public function testEndShiftSuccess(): void
    {
        $user = $this->createDriverUser();
        $request = new Request();
        $request->setUser($user);
        $this->endDriverShift->method('execute')->willReturn([
            'shift_id' => 50,
            'driver_id' => 5,
            'is_on_duty' => false,
            'message' => 'Turno finalizado con éxito.',
        ]);

        $res = $this->capture(fn() => $this->controller->endShift($request));
        $this->assertSame(200, $res['code']);
        $this->assertFalse($res['data']['is_on_duty']);
    }

    public function testUpdateLocationInvalidCoordsReturns422(): void
    {
        $user = $this->createDriverUser();
        $_POST = ['latitude' => 'invalid'];
        $request = new Request();
        $request->setUser($user);
        $res = $this->capture(fn() => $this->controller->updateLocation($request));
        $this->assertSame(422, $res['code']);
    }

    public function testUpdateLocationSuccess(): void
    {
        $user = $this->createDriverUser();
        $_POST = ['latitude' => 10.48, 'longitude' => -66.90, 'speed_kmh' => 30.5];
        $request = new Request();
        $request->setUser($user);

        $this->updateDriverLocation->method('execute')->willReturn([
            'status' => 'success',
            'driver_id' => 5,
            'crane_id' => 1,
            'coordinates' => ['latitude' => 10.48, 'longitude' => -66.90],
            'speed_kmh' => 30.5,
            'recorded_at' => '2026-10-23 20:30:00',
        ]);

        $res = $this->capture(fn() => $this->controller->updateLocation($request));
        $this->assertSame(200, $res['code']);
        $this->assertSame(10.48, $res['data']['coordinates']['latitude']);
    }
}
