<?php

namespace Tests\Unit\Application;

use App\Application\UseCases\RegisterVehicle;
use App\Domain\Entities\User;
use App\Domain\Entities\Vehicle;
use App\Domain\Enums\DocumentStatus;
use App\Domain\Enums\DocumentType;
use App\Domain\Enums\VehicleStatus;
use App\Domain\ValueObjects\BirthDate;
use App\Domain\ValueObjects\Email;
use App\Domain\ValueObjects\Money;
use App\Domain\ValueObjects\Names;
use App\Domain\ValueObjects\NationalId;
use App\Domain\ValueObjects\Niv;
use App\Domain\ValueObjects\Password;
use App\Domain\ValueObjects\Phone;
use App\Domain\ValueObjects\PlateNumber;
use App\Domain\ValueObjects\Surnames;
use App\Domain\ValueObjects\Uuid;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryDocumentRepository;
use Tests\Doubles\InMemoryUserRepository;
use Tests\Doubles\InMemoryVehicleRepository;

class RegisterVehicleTest extends TestCase
{
    private InMemoryVehicleRepository $vehicleRepo;
    private InMemoryUserRepository $userRepo;
    private InMemoryDocumentRepository $docRepo;
    private RegisterVehicle $useCase;
    private string $tempCarnetFile;

    protected function setUp(): void
    {
        $this->vehicleRepo = new InMemoryVehicleRepository();
        $this->userRepo = new InMemoryUserRepository();
        $this->docRepo = new InMemoryDocumentRepository();
        $this->useCase = new RegisterVehicle($this->vehicleRepo, $this->userRepo, $this->docRepo);

        $user = new User(
            id: 1,
            uuid: Uuid::generate(),
            names: new Names('Pedro'),
            surnames: new Surnames('Ramirez'),
            email: new Email('pedro@example.com'),
            password: Password::fromPlainText('ClaveSegura123!'),
            phone: new Phone('+584147778899'),
            walletBalance: new Money(0.00, 'VES'),
            identificationCard: new NationalId('V-21777888'),
            birthDate: new BirthDate('1991-09-12')
        );
        $this->userRepo->save($user);

        $this->tempCarnetFile = tempnam(sys_get_temp_dir(), 'test_carnet_');
        file_put_contents($this->tempCarnetFile, 'dummy carnet content');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempCarnetFile)) {
            @unlink($this->tempCarnetFile);
        }
    }

    public function test_registers_vehicle_successfully_with_uuid_and_carnet_document(): void
    {
        $result = $this->useCase->execute(
            userId: 1,
            rawPlate: 'AA111BB',
            rawNiv: '8X123456789012345',
            year: 2016,
            color: 'Blanco Puro',
            rawEngineSerial: 'ENG123456',
            brandId: 1,
            modelId: 1,
            brandName: 'Toyota',
            modelName: 'Corolla',
            carnetFilePath: $this->tempCarnetFile
        );

        $this->assertNotEmpty($result['id']); // UUID v7
        $this->assertEquals('AA111BB', $result['plate_number']);
        $this->assertEquals('8X123456789012345', $result['niv']);
        $this->assertEquals('ENG123456', $result['engine_serial']);
        $this->assertEquals(2016, $result['year']);
        $this->assertEquals('Toyota', $result['brand']);
        $this->assertEquals('Corolla', $result['model']);
        $this->assertEquals(VehicleStatus::Pending->getValue(), $result['status']);
        $this->assertFalse($result['is_verified']);

        // Verificar que el documento polimórfico del carnet se guardó en documents
        $doc = $this->docRepo->findLatestByEntity('vehicle', 1, DocumentType::VehicleRegistration);
        $this->assertNotNull($doc);
        $this->assertEquals(DocumentStatus::Pending, $doc->getStatus());
    }

    public function test_throws_409_when_plate_already_exists(): void
    {
        $existing = Vehicle::create(
            userId: 2,
            plateNumber: new PlateNumber('AA111BB'),
            niv: new Niv('9Y999999999999999'),
            engineSerial: null,
            year: 2010,
            color: 'Rojo'
        );
        $this->vehicleRepo->save($existing);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(409);
        $this->expectExceptionMessage('Ya existe un vehículo registrado con la placa AA111BB');

        $this->useCase->execute(
            userId: 1,
            rawPlate: 'AA111BB',
            rawNiv: '8X123456789012345',
            year: 2016,
            color: 'Blanco'
        );
    }

    public function test_throws_409_when_niv_already_exists(): void
    {
        $existing = Vehicle::create(
            userId: 2,
            plateNumber: new PlateNumber('ZZ999YY'),
            niv: new Niv('8X123456789012345'),
            engineSerial: null,
            year: 2010,
            color: 'Negro'
        );
        $this->vehicleRepo->save($existing);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(409);
        $this->expectExceptionMessage('Ya existe un vehículo registrado con el N.I.V 8X123456789012345');

        $this->useCase->execute(
            userId: 1,
            rawPlate: 'BB222CC',
            rawNiv: '8X123456789012345',
            year: 2016,
            color: 'Azul'
        );
    }
}
