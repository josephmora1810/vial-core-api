<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Application\Vehicles\UseCases\{
    DeleteVehicle,
    ListUserVehicles,
    RegisterVehicle,
    ScanVehicleRegistration
};
use App\Controllers\VehicleController;
use App\Core\{Request, Response};
use App\Domain\Auth\{Entities\Role, ValueObjects\Password};
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
use App\Domain\Vehicles\Repositories\{
    VehicleCatalogRepositoryInterface,
    VehicleRepositoryInterface
};
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;

final class VehicleControllerTest extends TestCase
{
    private RegisterVehicle $registerVehicle;
    private ListUserVehicles $listUserVehicles;
    private DeleteVehicle $deleteVehicle;
    private ScanVehicleRegistration $scanVehicleRegistration;
    private VehicleCatalogRepositoryInterface $catalogRepo;
    private VehicleRepositoryInterface $vehicleRepo;
    private VehicleController $controller;

    protected function setUp(): void
    {
        Response::$shouldExit = false;
        $_POST = [];
        $_GET = [];
        $_FILES = [];

        $this->registerVehicle = $this->createStub(RegisterVehicle::class);
        $this->listUserVehicles = $this->createStub(ListUserVehicles::class);
        $this->deleteVehicle = $this->createStub(DeleteVehicle::class);
        $this->scanVehicleRegistration = $this->createStub(ScanVehicleRegistration::class);
        $this->catalogRepo = $this->createStub(VehicleCatalogRepositoryInterface::class);
        $this->vehicleRepo = $this->createStub(VehicleRepositoryInterface::class);

        $this->controller = new VehicleController(
            registerVehicle: $this->registerVehicle,
            listUserVehicles: $this->listUserVehicles,
            deleteVehicle: $this->deleteVehicle,
            scanVehicleRegistration: $this->scanVehicleRegistration,
            catalogRepository: $this->catalogRepo,
            vehicleRepository: $this->vehicleRepo
        );
    }

    protected function tearDown(): void
    {
        Response::$shouldExit = true;
        $_POST = [];
        $_GET = [];
        $_FILES = [];
    }

    private function createUser(int $id = 1, string $role = 'client'): User
    {
        return new User(
            id: $id,
            uuid: Uuid::generate(),
            names: new Names('Luis'),
            surnames: new Surnames('Mendoza'),
            email: new Email('luis@example.com'),
            password: Password::fromPlainText('SecurePass123!'),
            phone: new Phone('+584145556677'),
            walletBalance: new Money(0, 'VES'),
            identificationCard: new NationalId('V-15678901'),
            birthDate: new BirthDate('1988-11-25'),
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            consentAt: new DateTimeImmutable(),
            deletedAt: null,
            isActive: true,
            roles: [new Role(1, $role)]
        );
    }

    private function capture(callable $callback): array
    {
        ob_start();
        try {
            $callback();
        } finally {
            $output = (string)ob_get_clean();
        }
        return json_decode($output, true) ?: [];
    }

    public function testIndexUnauthorized(): void
    {
        $res = $this->capture(fn() => $this->controller->index(new Request()));
        $this->assertSame(401, $res['code']);
    }

    public function testIndexSuccess(): void
    {
        $request = new Request();
        $request->setUser($this->createUser());

        $this->listUserVehicles->method('execute')->willReturn([
            ['uuid' => 'v-1', 'plate_number' => 'AB123CD', 'brand' => 'Toyota'],
        ]);

        $res = $this->capture(fn() => $this->controller->index($request));
        $this->assertSame(200, $res['code']);
        $this->assertCount(1, $res['data']);
    }

    public function testShowUnauthorized(): void
    {
        $validUuid = Uuid::generate()->getValue();
        $res = $this->capture(fn() => $this->controller->show(new Request(), $validUuid));
        $this->assertSame(401, $res['code']);
    }

    public function testShowNotFound(): void
    {
        $request = new Request();
        $request->setUser($this->createUser());
        $validUuid = Uuid::generate()->getValue();

        $this->vehicleRepo->method('findDetailedByUuid')->willReturn(null);

        $res = $this->capture(fn() => $this->controller->show($request, $validUuid));
        $this->assertSame(404, $res['code']);
    }

    public function testShowForbiddenWhenOtherUserVehicle(): void
    {
        $request = new Request();
        $request->setUser($this->createUser(1, 'client'));
        $validUuid = Uuid::generate()->getValue();

        $this->vehicleRepo->method('findDetailedByUuid')->willReturn([
            'uuid'         => $validUuid,
            'user_id'      => 999, // Another user!
            'plate_number' => 'AB123CD',
            'niv'          => '8X1ABCDEF12345678',
            'year'         => 2020,
            'color'        => 'Azul',
            'brand_name'   => 'Toyota',
            'model_name'   => 'Corolla',
        ]);

        $res = $this->capture(fn() => $this->controller->show($request, $validUuid));
        $this->assertSame(403, $res['code']);
    }

    public function testShowSuccess(): void
    {
        $request = new Request();
        $user = $this->createUser(1, 'client');
        $request->setUser($user);
        $validUuid = Uuid::generate()->getValue();

        $this->vehicleRepo->method('findDetailedByUuid')->willReturn([
            'uuid'         => $validUuid,
            'user_id'      => 1,
            'plate_number' => 'AB123CD',
            'niv'          => '8X1ABCDEF12345678',
            'year'         => 2020,
            'color'        => 'Azul',
            'brand_name'   => 'Toyota',
            'model_name'   => 'Corolla',
        ]);

        $res = $this->capture(fn() => $this->controller->show($request, $validUuid));
        $this->assertSame(200, $res['code']);
        $this->assertSame('Toyota', $res['data']['brand']);
    }

    public function testScanMissingFileReturns422(): void
    {
        $request = new Request();
        $request->setUser($this->createUser());
        $_FILES = [];

        $res = $this->capture(fn() => $this->controller->scan($request));
        $this->assertSame(422, $res['code']);
    }

    public function testScanSuccessWithFilePath(): void
    {
        $request = new Request();
        $request->setUser($this->createUser());
        $_POST = ['file_path' => 'C:/dummy/carnet.jpg'];

        $this->scanVehicleRegistration->method('execute')->willReturn([
            'suggested_vehicle' => ['plate_number' => 'AB123CD'],
            'document_type'     => 'vehicle_registration',
            'warnings'          => [],
            'raw_ocr'           => [],
            'file_path'         => 'C:/dummy/carnet.jpg',
            'message'           => 'Documento escaneado.',
        ]);

        $res = $this->capture(fn() => $this->controller->scan($request));
        $this->assertSame(200, $res['code']);
        $this->assertSame('AB123CD', $res['data']['suggested_vehicle']['plate_number']);
    }

    public function testStoreValidationFailure(): void
    {
        $request = new Request();
        $request->setUser($this->createUser());
        $_POST = [];

        $res = $this->capture(fn() => $this->controller->store($request));
        $this->assertSame(422, $res['code']);
    }

    public function testStoreSuccess(): void
    {
        $request = new Request();
        $request->setUser($this->createUser());
        $_POST = [
            'plate_number' => 'AB123CD',
            'year'         => 2020,
            'color'        => 'Negro',
            'brand_name'   => 'Toyota',
            'model_name'   => 'Corolla',
        ];

        $this->registerVehicle->method('execute')->willReturn([
            'uuid'         => 'v-new-123',
            'plate_number' => 'AB123CD',
            'year'         => 2020,
            'color'        => 'Negro',
        ]);

        $res = $this->capture(fn() => $this->controller->store($request));
        $this->assertSame(201, $res['code']);
        $this->assertSame('AB123CD', $res['data']['plate_number']);
    }

    public function testDestroySuccess(): void
    {
        $request = new Request();
        $request->setUser($this->createUser());

        $this->deleteVehicle->method('execute')->willReturn(true);

        $res = $this->capture(fn() => $this->controller->destroy($request, 'v-new-123'));
        $this->assertSame(200, $res['code']);
        $this->assertSame('v-new-123', $res['data']['id']);
        $this->assertTrue($res['data']['deleted']);
    }

    public function testCatalogClasses(): void
    {
        $this->catalogRepo->method('getClasses')->willReturn([['id' => 1, 'name' => 'Automovil']]);
        $res = $this->capture(fn() => $this->controller->classes(new Request()));
        $this->assertSame(200, $res['code']);
        $this->assertCount(1, $res['data']);
    }

    public function testCatalogSubclasses(): void
    {
        $_GET = ['class_id' => 1];
        $this->catalogRepo->method('getSubclasses')->willReturn([['id' => 1, 'name' => 'Sedan']]);
        $res = $this->capture(fn() => $this->controller->subclasses(new Request()));
        $this->assertSame(200, $res['code']);
        $this->assertCount(1, $res['data']);
    }

    public function testCatalogBrands(): void
    {
        $this->catalogRepo->method('getBrands')->willReturn([['id' => 1, 'name' => 'Toyota']]);
        $res = $this->capture(fn() => $this->controller->brands(new Request()));
        $this->assertSame(200, $res['code']);
        $this->assertCount(1, $res['data']);
    }

    public function testCatalogModelsMissingBrandId(): void
    {
        $_GET = [];
        $res = $this->capture(fn() => $this->controller->models(new Request()));
        $this->assertSame(422, $res['code']);
    }

    public function testCatalogModelsSuccess(): void
    {
        $_GET = ['brand_id' => 1];
        $this->catalogRepo->method('getModelsByBrand')->willReturn([['id' => 10, 'name' => 'Corolla']]);
        $res = $this->capture(fn() => $this->controller->models(new Request()));
        $this->assertSame(200, $res['code']);
        $this->assertCount(1, $res['data']);
    }
}
