<?php

namespace Tests\Unit\Application;

use App\Application\Driver\UseCases\GetDriverFleetStatus;
use App\Application\Driver\UseCases\RegisterDriverCrane;
use App\Application\Vehicles\UseCases\RegisterVehicle;
use App\Domain\Auth\Entities\Role;
use App\Domain\Auth\Enums\DocumentStatus;
use App\Domain\Auth\Enums\DocumentType;
use App\Domain\Auth\ValueObjects\Password;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Users\Entities\User;
use App\Domain\Users\Enums\UserRole;
use App\Domain\Users\ValueObjects\BirthDate;
use App\Domain\Users\ValueObjects\Email;
use App\Domain\Users\ValueObjects\Names;
use App\Domain\Users\ValueObjects\NationalId;
use App\Domain\Users\ValueObjects\Phone;
use App\Domain\Users\ValueObjects\Surnames;
use App\Domain\Vehicles\Enums\VehicleStatus;
use App\Domain\Vehicles\Enums\VehicleType;
use App\Domain\Vehicles\Services\VehicleMatcherService;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\FakeDocumentExtractor;
use Tests\Doubles\InMemoryDocumentRepository;
use Tests\Doubles\InMemoryUserRepository;
use Tests\Doubles\InMemoryVehicleCatalogRepository;
use Tests\Doubles\InMemoryVehicleRepository;

class VehicleRegistrationScenariosTest extends TestCase
{
    private InMemoryVehicleRepository $vehicleRepo;
    private InMemoryUserRepository $userRepo;
    private InMemoryDocumentRepository $docRepo;
    private InMemoryVehicleCatalogRepository $catalogRepo;
    private VehicleMatcherService $matcherService;
    private RegisterVehicle $registerVehicleUseCase;

    private User $client;
    private User $driver;
    private User $seller;

    protected function setUp(): void
    {
        $this->vehicleRepo = new InMemoryVehicleRepository();
        $this->userRepo = new InMemoryUserRepository();
        $this->docRepo = new InMemoryDocumentRepository();
        $this->catalogRepo = new InMemoryVehicleCatalogRepository();
        $this->matcherService = new VehicleMatcherService($this->catalogRepo);
        $this->registerVehicleUseCase = new RegisterVehicle($this->vehicleRepo, $this->userRepo, $this->docRepo);

        // Cliente
        $this->client = new User(
            id: 1,
            uuid: Uuid::generate(),
            names: new Names('Carlos'),
            surnames: new Surnames('Mendoza'),
            email: new Email('carlos.mendoza@cliente.com'),
            password: Password::fromPlainText('ClaveCliente123!'),
            phone: new Phone('+584141112233'),
            walletBalance: new Money(0.00, 'VES'),
            identificationCard: new NationalId('V-19888777'),
            birthDate: new BirthDate('1990-05-10'),
            roles: [new Role(1, UserRole::Client->getValue(), 'api')]
        );
        $this->userRepo->save($this->client);

        // Gruero / Conductor
        $this->driver = new User(
            id: 2,
            uuid: Uuid::generate(),
            names: new Names('Juan'),
            surnames: new Surnames('Perez'),
            email: new Email('juan.perez@gruero.com'),
            password: Password::fromPlainText('ClaveGruero123!'),
            phone: new Phone('+584124445566'),
            walletBalance: new Money(0.00, 'VES'),
            identificationCard: new NationalId('V-15666777'),
            birthDate: new BirthDate('1985-08-20'),
            roles: [new Role(2, UserRole::Driver->getValue(), 'api')]
        );
        $this->userRepo->save($this->driver);

        // Vendedora
        $this->seller = new User(
            id: 3,
            uuid: Uuid::generate(),
            names: new Names('Valentina'),
            surnames: new Surnames('Rivas'),
            email: new Email('valentina.rivas@ventas.com'),
            password: Password::fromPlainText('ClaveVendedora123!'),
            phone: new Phone('+584249998877'),
            walletBalance: new Money(0.00, 'VES'),
            identificationCard: new NationalId('V-22333444'),
            birthDate: new BirthDate('1995-11-05'),
            roles: [new Role(3, UserRole::Seller->getValue(), 'api')]
        );
        $this->userRepo->save($this->seller);
    }

    /**
     * Escenario 1: Registro vía OCR
     * - Si el OCR detecta correctamente marca/modelo, el matcher lo asocia automáticamente.
     * - Si el OCR tiene errores tipográficos (ej: "Berr" en vez de "Bera"), NO crea registros "en caliente".
     *   Devuelve is_matched = false y model_id = null para que el usuario elija del catálogo autocomplete.
     */
    public function testScenario1_OcrRegistrationMatchingAndTypoFallback(): void
    {
        // 1.1 Escaneo con error OCR ("Berr 150")
        $ocrTypoResponse = [
            'plate_number' => 'AA123BB',
            'niv' => '8XBERR150TYPO9999',
            'brand' => 'Berr',
            'model' => '150',
            'year' => 2023,
            'color' => 'Azul'
        ];

        $matchedTypo = $this->matcherService->match($ocrTypoResponse);
        $this->assertFalse($matchedTypo['is_matched'], 'No debe autocoincidir con marcas erróneas');
        $this->assertNull($matchedTypo['model_id'], 'No debe inventar o asignar un model_id erróneo');
        $this->assertSame('Berr', $matchedTypo['brand_name']);

        // 1.2 Catálogo autocomplete encuentra la marca y modelo reales
        $matchingBrands = $this->catalogRepo->getBrands('Bera');
        $this->assertNotEmpty($matchingBrands);
        $realBrand = $matchingBrands[array_key_first($matchingBrands)];
        $this->assertSame('Bera', $realBrand['name']);

        $matchingModels = $this->catalogRepo->getModelsByBrand($realBrand['id'], '150');
        $this->assertNotEmpty($matchingModels);
        $realModel = $matchingModels[0];
        $this->assertSame('150', $realModel['name']);

        // 1.3 El usuario confirma el registro usando el model_id verificado
        $registeredVehicle = $this->registerVehicleUseCase->execute(
            userId: $this->client->getId(),
            rawPlate: $matchedTypo['plate_number'],
            rawNiv: $matchedTypo['niv'],
            year: $matchedTypo['year'],
            color: $matchedTypo['color'],
            modelId: $realModel['id']
        );

        // Verificamos que el DTO devuelva exclusivamente UUID (sin ID numérico secuencial)
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $registeredVehicle['uuid']);
        $this->assertSame($registeredVehicle['uuid'], $registeredVehicle['id']);
        $this->assertSame('AA123BB', $registeredVehicle['plate_number']);
        $this->assertSame('8XBERR150TYPO9999', $registeredVehicle['niv']);
        $this->assertSame(VehicleStatus::Pending->getValue(), $registeredVehicle['status']);
    }

    /**
     * Escenario 2: Registro Manual vía Formulario (Carnet vs Certificado NIV)
     * - Carnet de Circulación: Puede no tener NIV legible -> Solo Placa.
     * - Certificado de Origen / NIV: Vehículo nuevo sin placas asignadas aún -> Solo NIV.
     * - Si faltan ambos -> Error 422.
     */
    public function testScenario2_ManualFormRegistrationNullabilityRules(): void
    {
        // 2.1 Registro solo con Placa (NIV nulo)
        $vehicleByPlate = $this->registerVehicleUseCase->execute(
            userId: $this->client->getId(),
            rawPlate: 'AB789CD',
            rawNiv: null,
            year: 2017,
            color: 'Blanco',
            modelId: 1
        );
        $this->assertSame('AB789CD', $vehicleByPlate['plate_number']);
        $this->assertNull($vehicleByPlate['niv']);

        // 2.2 Registro solo con NIV (Placa nula) - NIV debe tener exactamente 17 caracteres
        $vehicleByNiv = $this->registerVehicleUseCase->execute(
            userId: $this->client->getId(),
            rawPlate: null,
            rawNiv: '8XNIVTITLE2024001',
            year: 2024,
            color: 'Gris',
            modelId: 1
        );
        $this->assertNull($vehicleByNiv['plate_number']);
        $this->assertSame('8XNIVTITLE2024001', $vehicleByNiv['niv']);

        // 2.3 Registro sin Placa ni NIV debe fallar con 422
        $this->expectException(Exception::class);
        $this->expectExceptionCode(422);
        $this->expectExceptionMessage('Debe suministrar al menos la Placa o el N.I.V. del vehículo.');

        $this->registerVehicleUseCase->execute(
            userId: $this->client->getId(),
            rawPlate: null,
            rawNiv: null,
            year: 2020,
            color: 'Rojo',
            modelId: 1
        );
    }

    /**
     * Escenario 3: Registro de Flota de Grúas por parte de un Gruero (Driver)
     * - Un conductor / empresa de grúas puede registrar múltiples unidades (grúa plataforma, grúa gancho).
     * - Cada grúa se asocia a su user_id y queda con tipo CRANE y estado PENDING hasta revisión documental.
     */
    public function testScenario3_DriverRegistersMultipleCranesForFleet(): void
    {
        $tempCarnet1 = tempnam(sys_get_temp_dir(), 'carnet_crane1_');
        $tempCarnet2 = tempnam(sys_get_temp_dir(), 'carnet_crane2_');
        file_put_contents($tempCarnet1, 'dummy carnet crane 1');
        file_put_contents($tempCarnet2, 'dummy carnet crane 2');

        $registerCraneUseCase = new RegisterDriverCrane(
            vehicleRepository: $this->vehicleRepo,
            userRepository: $this->userRepo,
            documentRepository: $this->docRepo,
            documentExtractor: new FakeDocumentExtractor(),
            catalogRepository: $this->catalogRepo
        );

        // Grúa 1: Plataforma
        $crane1 = $registerCraneUseCase->execute(
            driverUserId: $this->driver->getId(),
            rawPlate: 'AA111AA',
            rawNiv: '8XCRANEPLATAFOR01',
            rawYear: 2019,
            rawColor: 'Amarillo',
            rawEngineSerial: 'ENG-CRANE-1',
            rawBrand: 'Ford',
            rawModel: 'F-350',
            brandId: 3,
            modelId: 1,
            carnetFilePath: $tempCarnet1
        );

        // Grúa 2: Gancho / Wheel-lift
        $crane2 = $registerCraneUseCase->execute(
            driverUserId: $this->driver->getId(),
            rawPlate: 'AA222BB',
            rawNiv: '8XCRANEGANCHO0002',
            rawYear: 2021,
            rawColor: 'Blanco',
            rawEngineSerial: 'ENG-CRANE-2',
            rawBrand: 'Chevrolet',
            rawModel: 'Kodiak',
            brandId: 2,
            modelId: 1,
            carnetFilePath: $tempCarnet2
        );

        $this->assertNotEmpty($crane1['crane']['uuid']);
        $this->assertNotEmpty($crane2['crane']['uuid']);
        $this->assertNotSame($crane1['crane']['uuid'], $crane2['crane']['uuid']);

        // Verificar el estado de la flota del gruero
        $fleetStatusUseCase = new GetDriverFleetStatus($this->userRepo, $this->docRepo, $this->vehicleRepo);
        $fleetStatus = $fleetStatusUseCase->execute($this->driver->getId());

        $this->assertSame(2, $fleetStatus['cranes_count']);
        $this->assertCount(2, $fleetStatus['cranes']);
        $this->assertFalse($fleetStatus['can_operate'], 'No puede aceptar servicios hasta que sus grúas sean aprobadas por el admin');

        @unlink($tempCarnet1);
        @unlink($tempCarnet2);
    }

    /**
     * Escenario 4: Registro de Vehículo por parte de una Vendedora para un Cliente
     * - La vendedora vende un contrato de asistencia vial a un cliente y registra el vehículo en su nombre.
     * - El vehículo queda asignado al cliente (user_id del cliente), NO a la vendedora.
     */
    public function testScenario4_SellerRegistersVehicleForClient(): void
    {
        $vehicle = $this->registerVehicleUseCase->execute(
            userId: $this->client->getId(), // Asignado al cliente
            rawPlate: 'AC987ZZ',
            rawNiv: '8XCLIENTEAUTOVIP9',
            year: 2022,
            color: 'Gris Plomo',
            brandId: 1,
            modelId: 1,
            brandName: 'Toyota',
            modelName: 'Corolla'
        );

        $this->assertNotEmpty($vehicle['uuid']);
        $this->assertSame('AC987ZZ', $vehicle['plate_number']);
        $this->assertSame('8XCLIENTEAUTOVIP9', $vehicle['niv']);

        // Verificamos que pertenezca a la cuenta del cliente
        $clientVehicles = $this->vehicleRepo->findAllByUserId($this->client->getId());
        $this->assertCount(1, $clientVehicles);
        $this->assertSame($this->client->getId(), $clientVehicles[0]->getUserId());

        // Verificamos que la vendedora no tiene vehículos a su nombre
        $sellerVehicles = $this->vehicleRepo->findAllByUserId($this->seller->getId());
        $this->assertCount(0, $sellerVehicles);
    }
}
