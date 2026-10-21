<?php

namespace Tests\Unit\Application\UseCases;

use App\Application\Contracts\UseCases\CancelServiceContract;
use App\Application\Contracts\UseCases\ExpireOverdueContracts;
use App\Application\Contracts\UseCases\GenerateServiceContractPdf;
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
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryPlanRepository;
use Tests\Doubles\InMemorySavedAddressRepository;
use Tests\Doubles\InMemoryServiceContractRepository;
use Tests\Doubles\InMemoryUserRepository;
use Tests\Doubles\InMemoryVehicleCatalogRepository;
use Tests\Doubles\InMemoryVehicleRepository;

class ContractPdfAndExpirationTest extends TestCase
{
    private InMemoryServiceContractRepository $contractRepo;
    private InMemoryPlanRepository $planRepo;
    private InMemoryUserRepository $userRepo;
    private InMemoryVehicleRepository $vehicleRepo;
    private InMemorySavedAddressRepository $addressRepo;
    private InMemoryVehicleCatalogRepository $catalogRepo;
    private GenerateServiceContractPdf $generatePdfUseCase;
    private ExpireOverdueContracts $expireUseCase;
    private CancelServiceContract $cancelUseCase;

    protected function setUp(): void
    {
        $this->contractRepo = new InMemoryServiceContractRepository();
        $this->planRepo = new InMemoryPlanRepository();
        $this->userRepo = new InMemoryUserRepository();
        $this->vehicleRepo = new InMemoryVehicleRepository();
        $this->addressRepo = new InMemorySavedAddressRepository();
        $this->catalogRepo = new InMemoryVehicleCatalogRepository();

        $this->generatePdfUseCase = new GenerateServiceContractPdf(
            $this->contractRepo,
            $this->planRepo,
            $this->userRepo,
            $this->vehicleRepo,
            $this->addressRepo,
            $this->catalogRepo,
            new FpdfContractPdfGenerator()
        );

        $this->expireUseCase = new ExpireOverdueContracts($this->contractRepo);
        $this->cancelUseCase = new CancelServiceContract(
            $this->contractRepo,
            $this->planRepo,
            $this->vehicleRepo
        );
    }

    public function testGeneratesPdfForPendingActiveAndExpiredContracts(): void
    {
        $user = User::create(
            Uuid::v7(),
            new Names('María'),
            new Surnames('González'),
            new Email('maria@ubikgo.com'),
            null,
            new Phone('04121234567'),
            new NationalId('V-20123456')
        );
        $this->userRepo->save($user);

        $plan = new Plan('Plan Básico Vial', 35.00, 12, 3, 60.0, 'Plan de prueba');
        $this->planRepo->save($plan);

        $vehicle = Vehicle::create(
            $user->getId(),
            new PlateNumber('AA111BB'),
            new Niv('8Z1ABCDE987654321'),
            new EngineSerial('1NZ1234567'),
            2021,
            'PLATA',
            1,
            1,
            1,
            1,
            'TOYOTA',
            'YARIS',
            true
        );
        $this->vehicleRepo->save($vehicle);

        $contract = new ServiceContract(
            clientId: $user->getId(),
            planId: $plan->getId(),
            remainingServices: 3,
            remainingKm: 60.0,
            vehicleId: $vehicle->getId(),
            status: ContractStatus::PendingPayment
        );
        $this->contractRepo->save($contract);

        // 1. Descarga en PENDING_PAYMENT (con marca de agua NO PAGADO)
        $resPending = $this->generatePdfUseCase->execute($contract->getUuid()->getValue(), 'http://localhost:8088');
        $this->assertStringStartsWith('%PDF-1.', $resPending['pdf_binary']);
        $this->assertSame('PENDING_PAYMENT', $resPending['status']);
        $this->assertStringContainsString('Contrato-UbikGo-', $resPending['filename']);

        // 2. Activar contrato y descargar en ACTIVE (sin marca de agua, con sello PAGADO)
        $contract->activate(12);
        $this->contractRepo->save($contract);

        $resActive = $this->generatePdfUseCase->execute($contract->getUuid()->getValue(), 'http://localhost:8088');
        $this->assertStringStartsWith('%PDF-1.', $resActive['pdf_binary']);
        $this->assertSame('ACTIVE', $resActive['status']);

        // 3. Cancelar contrato y descargar en CANCELLED (con marca de agua CANCELADO y sello PAGADO)
        $cancelRes = $this->cancelUseCase->execute($contract->getUuid()->getValue(), $user->getId());
        $this->assertSame('CANCELLED', $cancelRes['status']);

        $resCancelled = $this->generatePdfUseCase->execute($contract->getUuid()->getValue(), 'http://localhost:8088');
        $this->assertStringStartsWith('%PDF-1.', $resCancelled['pdf_binary']);
        $this->assertSame('CANCELLED', $resCancelled['status']);
    }

    public function testCronjobExpiresOverdueActiveContracts(): void
    {
        $overdueContract = new ServiceContract(
            clientId: 1,
            planId: 1,
            remainingServices: 2,
            remainingKm: 50.0,
            vehicleId: 1,
            status: ContractStatus::Active,
            expiresAt: new DateTimeImmutable('-2 days')
        );
        $validContract = new ServiceContract(
            clientId: 1,
            planId: 1,
            remainingServices: 3,
            remainingKm: 80.0,
            vehicleId: 2,
            status: ContractStatus::Active,
            expiresAt: new DateTimeImmutable('+6 months')
        );

        $this->contractRepo->save($overdueContract);
        $this->contractRepo->save($validContract);

        $result = $this->expireUseCase->execute();

        $this->assertSame(1, $result['expired_count']);
        $this->assertSame(ContractStatus::Expired, $this->contractRepo->findById($overdueContract->getId())->getStatus());
        $this->assertSame(ContractStatus::Active, $this->contractRepo->findById($validContract->getId())->getStatus());
    }

    public function testRejectsNumericIdToPreventEnumerationAttack(): void
    {
        $contract = new ServiceContract(
            clientId: 1,
            planId: 1,
            remainingServices: 3,
            remainingKm: 80.0,
            vehicleId: 1,
            status: ContractStatus::Active,
            id: 42
        );
        $this->contractRepo->save($contract);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(404);

        // Intentar acceder por ID numérico en lugar de UUID o código de verificación
        $this->generatePdfUseCase->execute('42', 'http://localhost:8088');
    }
}
