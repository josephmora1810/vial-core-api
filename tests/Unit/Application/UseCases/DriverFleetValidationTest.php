<?php

namespace Tests\Unit\Application\UseCases;

use App\Application\Auth\UseCases\ReviewKycDocument;
use App\Application\Documents\UseCases\EnqueueDocumentS3Upload;
use App\Application\Driver\UseCases\GetDriverFleetStatus;
use App\Application\Driver\UseCases\RegisterDriverCrane;
use App\Application\Driver\UseCases\UploadDriverLicense;
use App\Domain\Auth\Entities\Document;
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
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\FakeDocumentExtractor;
use Tests\Doubles\InMemoryDocumentRepository;
use Tests\Doubles\InMemoryQueueService;
use Tests\Doubles\InMemoryUserRepository;
use Tests\Doubles\InMemoryVehicleRepository;

class DriverFleetValidationTest extends TestCase
{
    private InMemoryUserRepository $userRepo;
    private InMemoryVehicleRepository $vehicleRepo;
    private InMemoryDocumentRepository $docRepo;
    private FakeDocumentExtractor $docExtractor;
    private InMemoryQueueService $queueService;
    private EnqueueDocumentS3Upload $enqueueS3;

    private User $driver;
    private string $fakeCarnetPath;
    private string $fakeLicensePath;

    protected function setUp(): void
    {
        $this->userRepo = new InMemoryUserRepository();
        $this->vehicleRepo = new InMemoryVehicleRepository();
        $this->docRepo = new InMemoryDocumentRepository();
        $this->docExtractor = new FakeDocumentExtractor();
        $this->queueService = new InMemoryQueueService();
        $this->enqueueS3 = new EnqueueDocumentS3Upload($this->queueService);

        $this->fakeCarnetPath = sys_get_temp_dir() . '/test_carnet_' . bin2hex(random_bytes(4)) . '.jpg';
        file_put_contents($this->fakeCarnetPath, 'FAKE_CARNET_BYTES');

        $this->fakeLicensePath = sys_get_temp_dir() . '/test_license_' . bin2hex(random_bytes(4)) . '.jpg';
        file_put_contents($this->fakeLicensePath, 'FAKE_LICENSE_BYTES');

        // Crear usuario operador de grúa con rol 'driver'
        $this->driver = new User(
            id: null,
            uuid: Uuid::generate(),
            names: new Names('José Manuel'),
            surnames: new Surnames('Graterol'),
            email: new Email('gruero.jose@ubikgo.com'),
            password: Password::fromPlainText('GruaSegura2026*'),
            phone: new Phone('04147778899'),
            walletBalance: new Money(0, 'VES'),
            identificationCard: new NationalId('V-18765432'),
            birthDate: new BirthDate('1988-03-20'),
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            consentAt: new DateTimeImmutable(),
            deletedAt: null,
            isActive: true,
            roles: [new Role(null, UserRole::Driver->getValue(), 'api')],
            permissions: []
        );
        $this->userRepo->save($this->driver);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->fakeCarnetPath)) {
            @unlink($this->fakeCarnetPath);
        }
        if (file_exists($this->fakeLicensePath)) {
            @unlink($this->fakeLicensePath);
        }
    }

    public function testDriverRegistersCraneWithCarnetAndEnqueuesS3Upload(): void
    {
        $registerCrane = new RegisterDriverCrane(
            vehicleRepository: $this->vehicleRepo,
            userRepository: $this->userRepo,
            documentRepository: $this->docRepo,
            documentExtractor: $this->docExtractor,
            enqueueS3: $this->enqueueS3
        );

        $result = $registerCrane->execute(
            driverUserId: (int)$this->driver->getId(),
            rawPlate: 'AB123CD',
            rawNiv: '8Z1GRUA9988776655',
            rawYear: 2020,
            rawColor: 'BLANCO',
            rawEngineSerial: 'MOTOR-C350',
            rawBrand: 'CHEVROLET',
            rawModel: 'F-350 SILVERADO',
            carnetFilePath: $this->fakeCarnetPath,
            carnetDocType: 'vehicle_registration'
        );

        $this->assertNotNull($result['crane']['id']);
        $this->assertSame('AB123CD', $result['crane']['plate_number']);
        $this->assertSame(VehicleType::Crane->getValue(), $result['crane']['type']);
        $this->assertSame(VehicleStatus::Pending->getValue(), $result['crane']['status']);
        $this->assertFalse($result['crane']['is_verified']);
        $this->assertFalse($result['is_fleet_ready']);

        // Verificar que el carnet quedó registrado en documents y encolado en Redis para S3
        $this->assertNotNull($result['carnet_document_id']);
        $carnetDoc = $this->docRepo->findById($result['carnet_document_id']);
        $this->assertNotNull($carnetDoc);
        $this->assertSame('vehicle', $carnetDoc->getDocumentableType());
        $this->assertSame(DocumentStatus::Pending, $carnetDoc->getStatus());

        $this->assertSame(1, $this->queueService->size(EnqueueDocumentS3Upload::QUEUE_NAME));
        $job = $this->queueService->pop(EnqueueDocumentS3Upload::QUEUE_NAME);
        $this->assertSame($carnetDoc->getId(), $job['document_id']);
    }

    public function testDriverUploadsLicenseAndEnqueuesS3(): void
    {
        $uploadLicense = new UploadDriverLicense(
            documentRepository: $this->docRepo,
            userRepository: $this->userRepo,
            enqueueS3: $this->enqueueS3
        );

        $res = $uploadLicense->execute(
            driverUserId: (int)$this->driver->getId(),
            filePath: $this->fakeLicensePath,
            grade: '5TO GRADO',
            expirationDate: '2029-12-31'
        );

        $this->assertSame('driving_license', $res['document_type']);
        $this->assertSame('PENDING', $res['status']);
        $this->assertSame('5TO GRADO', $res['extracted_data']['grade']);

        $this->assertSame(1, $this->queueService->size(EnqueueDocumentS3Upload::QUEUE_NAME));
    }

    public function testDriverFleetStatusReportsPendingRequirementsBeforeApproval(): void
    {
        // 1. Cargar solo la grúa
        $registerCrane = new RegisterDriverCrane(
            vehicleRepository: $this->vehicleRepo,
            userRepository: $this->userRepo,
            documentRepository: $this->docRepo,
            documentExtractor: $this->docExtractor,
            enqueueS3: $this->enqueueS3
        );
        $registerCrane->execute(
            driverUserId: (int)$this->driver->getId(),
            rawPlate: 'AA999ZZ',
            rawNiv: '8Z1GRUA1122334455',
            rawYear: 2022,
            rawColor: 'AMARILLO',
            carnetFilePath: $this->fakeCarnetPath
        );

        $fleetStatusUseCase = new GetDriverFleetStatus(
            userRepository: $this->userRepo,
            documentRepository: $this->docRepo,
            vehicleRepository: $this->vehicleRepo
        );

        $status = $fleetStatusUseCase->execute((int)$this->driver->getId());

        $this->assertFalse($status['can_operate']);
        $this->assertFalse($status['is_fleet_ready']);
        $this->assertSame(1, $status['cranes_count']);
        // Requisitos pendientes: cédula, licencia y aprobación de la grúa
        $this->assertNotEmpty($status['pending_requirements']);
        $this->assertContains('Debe cargar su Cédula de Identidad.', $status['pending_requirements']);
        $this->assertContains('Debe cargar su Licencia de Conducir (4to o 5to grado).', $status['pending_requirements']);
    }

    public function testDriverBecomesFullyOperableWhenAdminReviewsAndApprovesAllDocuments(): void
    {
        $driverId = (int)$this->driver->getId();

        // 1. Cédula del Chofer
        $cedulaDoc = Document::create(
            documentableType: 'user',
            documentableId: $driverId,
            documentType: DocumentType::IdentityCard,
            filePath: $this->fakeCarnetPath,
            status: DocumentStatus::Pending
        );
        $this->docRepo->save($cedulaDoc);

        // 2. Licencia de Conducir
        $licenseDoc = Document::create(
            documentableType: 'user',
            documentableId: $driverId,
            documentType: DocumentType::DrivingLicense,
            filePath: $this->fakeLicensePath,
            extractedData: ['grade' => '5TO GRADO'],
            status: DocumentStatus::Pending
        );
        $this->docRepo->save($licenseDoc);

        // 3. Registrar Grúa
        $registerCrane = new RegisterDriverCrane(
            vehicleRepository: $this->vehicleRepo,
            userRepository: $this->userRepo,
            documentRepository: $this->docRepo,
            documentExtractor: $this->docExtractor
        );
        $craneResult = $registerCrane->execute(
            driverUserId: $driverId,
            rawPlate: 'GA888YZ',
            rawNiv: '8Z1GRUA7778889990',
            rawYear: 2021,
            rawColor: 'BLANCO',
            carnetFilePath: $this->fakeCarnetPath
        );
        $carnetDocId = $craneResult['carnet_document_id'];

        // 4. El Administrador revisa y aprueba los 3 documentos (FR-33)
        $adminReview = new ReviewKycDocument($this->docRepo, $this->vehicleRepo);
        $adminReview->execute((int)$cedulaDoc->getId(), 'VERIFIED');
        $adminReview->execute((int)$licenseDoc->getId(), 'VERIFIED');
        $adminReview->execute($carnetDocId, 'VERIFIED'); // Al aprobar carnet, la grúa pasa a is_verified = true y ACTIVE

        // 5. Consultar estado operativo
        $fleetStatusUseCase = new GetDriverFleetStatus(
            userRepository: $this->userRepo,
            documentRepository: $this->docRepo,
            vehicleRepository: $this->vehicleRepo
        );

        $status = $fleetStatusUseCase->execute($driverId);

        $this->assertTrue($status['can_operate']);
        $this->assertTrue($status['is_fleet_ready']);
        $this->assertSame('VERIFIED', $status['identity_card_status']);
        $this->assertSame('VERIFIED', $status['driving_license_status']);
        $this->assertCount(0, $status['pending_requirements']);
        $this->assertTrue($status['cranes'][0]['is_operable']);
        $this->assertSame('ACTIVE', $status['cranes'][0]['crane']['status']);
        $this->assertTrue($status['cranes'][0]['crane']['is_verified']);
    }

    public function testNonDriverUserCannotRegisterCrane(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(403);

        // Crear cliente común
        $client = new User(
            id: null,
            uuid: Uuid::generate(),
            names: new Names('Cliente'),
            surnames: new Surnames('Normal'),
            email: new Email('cliente@ubikgo.com'),
            password: Password::fromPlainText('ClaveValida123*'),
            phone: new Phone('04120001122'),
            walletBalance: new Money(0, 'VES'),
            identificationCard: new NationalId('V-11223344'),
            birthDate: new BirthDate('1995-01-01'),
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            consentAt: new DateTimeImmutable(),
            deletedAt: null,
            isActive: true,
            roles: [new Role(null, UserRole::Client->getValue(), 'api')],
            permissions: []
        );
        $this->userRepo->save($client);

        $registerCrane = new RegisterDriverCrane(
            vehicleRepository: $this->vehicleRepo,
            userRepository: $this->userRepo,
            documentRepository: $this->docRepo,
            documentExtractor: $this->docExtractor
        );

        $registerCrane->execute(
            driverUserId: (int)$client->getId(),
            rawPlate: 'CL123XX',
            rawNiv: '8Z1CLIENTE9988776',
            rawYear: 2022,
            rawColor: 'ROJO'
        );
    }
}
