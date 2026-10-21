<?php

namespace Tests\Unit\Application\UseCases;

use App\Application\Contracts\UseCases\VerifyServiceContract;
use App\Domain\Contracts\Entities\Plan;
use App\Domain\Contracts\Entities\ServiceContract;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Users\Entities\User;
use App\Domain\Users\ValueObjects\Email;
use App\Domain\Users\ValueObjects\Names;
use App\Domain\Users\ValueObjects\NationalId;
use App\Domain\Users\ValueObjects\Phone;
use App\Domain\Users\ValueObjects\Surnames;
use App\Domain\Vehicles\Entities\Vehicle;
use App\Domain\Vehicles\ValueObjects\EngineSerial;
use App\Domain\Vehicles\ValueObjects\Niv;
use App\Domain\Vehicles\ValueObjects\PlateNumber;
use App\Infrastructure\Pdf\FpdfContractPdfGenerator;
use App\Infrastructure\Security\PdfDigitalSignatureService;
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryPlanRepository;
use Tests\Doubles\InMemoryServiceContractRepository;
use Tests\Doubles\InMemoryUserRepository;
use Tests\Doubles\InMemoryVehicleRepository;

class ContractSecurityAndVerificationTest extends TestCase
{
    private InMemoryServiceContractRepository $contractRepo;
    private InMemoryPlanRepository $planRepo;
    private InMemoryUserRepository $userRepo;
    private InMemoryVehicleRepository $vehicleRepo;
    private VerifyServiceContract $verifyUseCase;

    protected function setUp(): void
    {
        $this->contractRepo = new InMemoryServiceContractRepository();
        $this->planRepo = new InMemoryPlanRepository();
        $this->userRepo = new InMemoryUserRepository();
        $this->vehicleRepo = new InMemoryVehicleRepository();

        $this->verifyUseCase = new VerifyServiceContract(
            $this->contractRepo,
            $this->planRepo,
            $this->userRepo,
            $this->vehicleRepo,
            null // Redis mock opcional, se prueba directo contra base de datos
        );
    }

    public function testVerificationCodeStrictFormat(): void
    {
        $contract = new ServiceContract(
            clientId: 1,
            planId: 1,
            remainingServices: 3,
            remainingKm: 80.0,
            vehicleId: 1,
            status: ContractStatus::Active,
            startsAt: new DateTimeImmutable('2026-10-21 14:00:00')
        );

        $code = $contract->generateVerificationCode();

        // Formato estricto: UBKMMYY-XXXX-ZZZZ (ej: UBK1026-8F2A-99C1)
        $this->assertMatchesRegularExpression('/^UBK1026-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code);
        $this->assertSame($code, $contract->getVerificationCode());
    }

    public function testHmacIntegrityCalculationAndTamperingDetection(): void
    {
        $contract = new ServiceContract(
            clientId: 10,
            planId: 2,
            remainingServices: 5,
            remainingKm: 120.0,
            vehicleId: 4,
            status: ContractStatus::Active
        );

        $secret = 'test-secret-key-123456';
        $originalHash = $contract->calculateIntegrityHash($secret, 'AB123CD');
        $this->assertSame(64, strlen($originalHash)); // SHA-256 hex string

        // Modificar artificialmente los servicios restantes (intento de alteración de contrato)
        $tamperedContract = new ServiceContract(
            clientId: 10,
            planId: 2,
            remainingServices: 99, // Alterado
            remainingKm: 120.0,
            vehicleId: 4,
            status: ContractStatus::Active
        );
        // Usar el mismo uuid para emular intento de falsificación
        $tamperedHash = $tamperedContract->calculateIntegrityHash($secret, 'AB123CD');

        $this->assertNotSame($originalHash, $tamperedHash);
    }

    public function testPdfDigitalSignatureAndIntegrityVerification(): void
    {
        $sigService = new PdfDigitalSignatureService();
        $pdfGenerator = new FpdfContractPdfGenerator(null, $sigService);

        $user = User::create(
            Uuid::v7(),
            new Names('Carlos'),
            new Surnames('Mendoza'),
            new Email('carlos@ubikgo.com'),
            null,
            new Phone('04147654321'),
            new NationalId('V-18765432')
        );
        $this->userRepo->save($user);

        $plan = new Plan('Plan Premium', 85.00, 12, 5, 120.0, 'Plan Todo Incluido');
        $this->planRepo->save($plan);

        $vehicle = Vehicle::create(
            $user->getId(),
            new PlateNumber('AB123CD'),
            new Niv('9BWZZZ377VT004251'),
            new EngineSerial('EA88812345'),
            2022,
            'NEGRO',
            1,
            1,
            1,
            1,
            'VOLKSWAGEN',
            'GOLF',
            true
        );
        $this->vehicleRepo->save($vehicle);

        $contract = new ServiceContract(
            clientId: $user->getId(),
            planId: $plan->getId(),
            remainingServices: 5,
            remainingKm: 120.0,
            vehicleId: $vehicle->getId(),
            status: ContractStatus::Active,
            startsAt: new DateTimeImmutable('2026-10-21 10:00:00')
        );
        $contract->generateVerificationCode();
        $contract->setIntegrityHash($contract->calculateIntegrityHash('ubikgo-secret-contract-key', 'AB123CD'));
        $this->contractRepo->save($contract);

        $signedPdf = $pdfGenerator->generate(
            contract: $contract,
            plan: $plan,
            client: $user,
            vehicle: $vehicle
        );

        $this->assertStringStartsWith('%PDF-', $signedPdf);
        $this->assertStringContainsString('%%EOF', $signedPdf);
        $this->assertStringContainsString('%UBIKGO-DIGITAL-SIGNATURE-START', $signedPdf);

        // Validar firma digital con el servicio criptográfico X.509
        $this->assertTrue($sigService->verify($signedPdf));

        // Corromper el PDF (alterar 1 solo byte en el cuerpo)
        $tamperedPdf = substr_replace($signedPdf, 'X', 100, 1);
        $this->assertFalse($sigService->verify($tamperedPdf));
    }

    public function testVerifyContractUseCaseAuthoritativeSuccess(): void
    {
        $user = User::create(
            Uuid::v7(),
            new Names('José'),
            new Surnames('Fernández'),
            new Email('jose@ubikgo.com'),
            null,
            new Phone('04241122334'),
            new NationalId('V-12345678')
        );
        $this->userRepo->save($user);

        $plan = new Plan('Plan Oro', 50.00, 12, 4, 80.0);
        $this->planRepo->save($plan);

        $vehicle = Vehicle::create(
            $user->getId(),
            new PlateNumber('AB987XY'),
            new Niv('1HGBH41JXMN109186'),
            new EngineSerial('K24A1234567'),
            2019,
            'AZUL',
            1,
            1,
            1,
            1,
            'HONDA',
            'CIVIC',
            true
        );
        $this->vehicleRepo->save($vehicle);

        $contract = new ServiceContract(
            clientId: $user->getId(),
            planId: $plan->getId(),
            remainingServices: 4,
            remainingKm: 80.0,
            vehicleId: $vehicle->getId(),
            status: ContractStatus::Active,
            startsAt: new DateTimeImmutable('2026-10-21 00:00:00'),
            expiresAt: new DateTimeImmutable('+1 year')
        );
        $code = $contract->generateVerificationCode();
        $secretKey = $_ENV['APP_KEY'] ?? 'ubikgo-secret-contract-key';
        $contract->setIntegrityHash($contract->calculateIntegrityHash($secretKey, 'AB987XY'));
        $this->contractRepo->save($contract);

        // Consulta pública oficial con placa coincidente
        $response = $this->verifyUseCase->execute($code, 'AB987XY');

        $this->assertSame('success', $response['status']);
        $this->assertTrue($response['is_valid']);
        $this->assertFalse($response['fraud_alert']);
        $this->assertSame($code, $response['contract']['verification_code']);
        $this->assertSame('ACTIVE', $response['contract']['status']);
        $this->assertSame('Plan Oro', $response['contract']['plan_name']);
        $this->assertSame('AB987XY', $response['contract']['vehicle']['plate']);
        $this->assertSame('J*** F****', $response['contract']['client_masked']['name']);
        $this->assertSame('V-***5678', $response['contract']['client_masked']['identification']);
        $this->assertTrue($response['contract']['integrity_verified']);
    }

    public function testVerifyContractUseCaseDetectsPlateFraud(): void
    {
        $user = User::create(
            Uuid::v7(),
            new Names('Pedro'),
            new Surnames('Pérez'),
            new Email('pedro@ubikgo.com'),
            null,
            new Phone('04123344556'),
            new NationalId('V-15667788')
        );
        $this->userRepo->save($user);

        $plan = new Plan('Plan Plata', 40.00, 12, 3, 50.0);
        $this->planRepo->save($plan);

        $vehicle = Vehicle::create(
            $user->getId(),
            new PlateNumber('AA123BB'),
            new Niv('1FAFP53U51A100001'),
            new EngineSerial('DURATEC1234'),
            2018,
            'BLANCO',
            1,
            1,
            1,
            1,
            'FORD',
            'FOCUS',
            true
        );
        $this->vehicleRepo->save($vehicle);

        $contract = new ServiceContract(
            clientId: $user->getId(),
            planId: $plan->getId(),
            remainingServices: 3,
            remainingKm: 50.0,
            vehicleId: $vehicle->getId(),
            status: ContractStatus::Active
        );
        $code = $contract->generateVerificationCode();
        $contract->setIntegrityHash($contract->calculateIntegrityHash($_ENV['APP_KEY'] ?? 'ubikgo-secret-contract-key', 'AA123BB'));
        $this->contractRepo->save($contract);

        // Escaneo o consulta con placa adulterada/falsificada
        $response = $this->verifyUseCase->execute($code, 'ZZ999YY');

        $this->assertSame('alert', $response['status']);
        $this->assertFalse($response['is_valid']);
        $this->assertTrue($response['fraud_alert']);
        $this->assertStringContainsString('ALERTA DE SEGURIDAD', $response['alert_message']);
    }

    public function testVerifyContractUseCaseRejectsNonexistentCode(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);
        $this->verifyUseCase->execute('UBK9999-FAKE-CODE');
    }
}
