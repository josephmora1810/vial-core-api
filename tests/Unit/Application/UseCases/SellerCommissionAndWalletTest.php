<?php

namespace Tests\Unit\Application\UseCases;

use App\Application\Commissions\UseCases\{
    GenerateOrderCommission,
    ListAdminCommissions,
    ListSellerCommissions,
    ManageSellerBankAccount,
    PayCommissionByAdmin,
    RequestCommissionWithdrawal,
    UpdateSellerCommissionRate
};
use App\Application\Payments\UseCases\{
    HandleBancaribeWebhook,
    ProcessPayment
};
use App\Domain\Auth\ValueObjects\Password;
use App\Domain\Commissions\{
    Entities\Commission,
    Entities\SellerProfile,
    Enums\CommissionStatus
};
use App\Domain\Contracts\{
    Entities\Plan,
    Entities\ServiceContract,
    Enums\ContractStatus
};
use App\Domain\Payments\{
    Entities\Order,
    Enums\OrderableType,
    Enums\OrderStatus
};
use App\Domain\Shared\ValueObjects\{Money, Uuid};
use App\Domain\Users\Entities\User;
use App\Domain\Users\ValueObjects\{BirthDate, Email, Names, NationalId, Phone, Surnames};
use DateTimeImmutable;
use DomainException;
use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\{
    FakeExchangeRateService,
    FakePaymentGateway,
    InMemoryCommissionRepository,
    InMemoryOrderRepository,
    InMemoryPaymentRepository,
    InMemoryPlanRepository,
    InMemorySellerProfileRepository,
    InMemoryServiceContractRepository,
    InMemoryUserRepository
};

class SellerCommissionAndWalletTest extends TestCase
{
    private InMemoryCommissionRepository $commissionRepo;
    private InMemorySellerProfileRepository $sellerProfileRepo;
    private InMemoryUserRepository $userRepo;
    private InMemoryOrderRepository $orderRepo;
    private InMemoryServiceContractRepository $contractRepo;
    private InMemoryPlanRepository $planRepo;
    private InMemoryPaymentRepository $paymentRepo;
    private FakePaymentGateway $paymentGateway;
    private FakeExchangeRateService $exchangeService;

    private User $seller;
    private User $client;
    private Plan $plan;

    protected function setUp(): void
    {
        $this->commissionRepo = new InMemoryCommissionRepository();
        $this->sellerProfileRepo = new InMemorySellerProfileRepository();
        $this->userRepo = new InMemoryUserRepository();
        $this->orderRepo = new InMemoryOrderRepository();
        $this->contractRepo = new InMemoryServiceContractRepository();
        $this->planRepo = new InMemoryPlanRepository();
        $this->paymentRepo = new InMemoryPaymentRepository();
        $this->paymentGateway = new FakePaymentGateway();
        $this->exchangeService = new FakeExchangeRateService();

        // Crear vendedora y cliente
        $this->seller = new User(
            id: 10,
            uuid: Uuid::generate(),
            names: new Names('Valeria'),
            surnames: new Surnames('Vendedora'),
            email: new Email('valeria@ubikgo.test'),
            password: Password::fromPlainText('ClaveValida1!'),
            phone: new Phone('+584141112233'),
            walletBalance: new Money(0.00, 'VES'),
            identificationCard: new NationalId('V-20111222'),
            birthDate: new BirthDate('1995-05-15')
        );
        $this->userRepo->save($this->seller);

        $this->client = new User(
            id: 20,
            uuid: Uuid::generate(),
            names: new Names('Carlos'),
            surnames: new Surnames('Cliente'),
            email: new Email('carlos@cliente.test'),
            password: Password::fromPlainText('ClaveValida1!'),
            phone: new Phone('+584245556677'),
            walletBalance: new Money(0.00, 'VES'),
            identificationCard: new NationalId('V-18999888'),
            birthDate: new BirthDate('1990-01-01')
        );
        $this->userRepo->save($this->client);

        // Crear plan
        $this->plan = new Plan(
            name: 'Plan Platino Anual',
            priceUsd: 150.00,
            durationMonths: 12,
            maxServices: 5,
            maxKm: 200.00,
            description: 'Cobertura ilimitada nacional',
            isActive: true,
            uuid: Uuid::generate(),
            id: 1
        );
        $this->planRepo->save($this->plan);
    }

    public function testSellerProfileEntityValidationsAndBankDetails(): void
    {
        $profile = new SellerProfile(userId: 10, commissionRate: 12.50);
        $this->assertSame(10, $profile->getUserId());
        $this->assertSame(12.50, $profile->getCommissionRate());
        $this->assertFalse($profile->hasCompleteBankDetails());

        // Validar tasa inválida
        $this->expectException(InvalidArgumentException::class);
        new SellerProfile(userId: 10, commissionRate: 150.0);
    }

    public function testSellerProfileUpdateBankDetails(): void
    {
        $profile = new SellerProfile(userId: 10, commissionRate: 10.00);

        // Actualizar datos bancarios válidos
        $profile->updateBankDetails(
            bankId: 1,
            accountNumber: '0102-0123-45-0123456789',
            accountType: 'corriente',
            accountHolderName: 'Valeria Vendedora',
            accountHolderId: 'V-20111222',
            phonePagoMovil: '04141112233'
        );

        $this->assertTrue($profile->hasCompleteBankDetails());
        $this->assertSame('01020123450123456789', $profile->getAccountNumber());
        $this->assertSame('CORRIENTE', $profile->getAccountType());
        $this->assertSame('Valeria Vendedora', $profile->getAccountHolderName());
        $this->assertSame('V-20111222', $profile->getAccountHolderId());
        $this->assertSame('04141112233', $profile->getPhonePagoMovil());
    }

    public function testCommissionLifecycleTransitions(): void
    {
        $comm = new Commission(
            sellerId: 10,
            orderId: 101,
            amountUsd: 15.00,
            percentage: 10.00,
            status: CommissionStatus::Pending,
            id: 1
        );

        $this->assertTrue($comm->getStatus()->isPending());

        // PENDING -> WITHDRAWAL_REQUESTED
        $comm->requestWithdrawal();
        $this->assertTrue($comm->getStatus()->isWithdrawalRequested());

        // Error al re-solicitar si no está PENDING
        $this->expectException(DomainException::class);
        $comm->requestWithdrawal();
    }

    public function testCommissionMarkAsPaidAndCancel(): void
    {
        $comm = new Commission(
            sellerId: 10,
            orderId: 101,
            amountUsd: 25.00,
            percentage: 15.00,
            status: CommissionStatus::WithdrawalRequested,
            id: 1
        );

        $comm->markAsPaid('REF-BANCO-998877');
        $this->assertTrue($comm->getStatus()->isPaid());
        $this->assertSame('REF-BANCO-998877', $comm->getPaymentReference());
        $this->assertNotNull($comm->getPaidAt());

        // No se puede pagar de nuevo
        $this->expectException(DomainException::class);
        $comm->markAsPaid('REF-OTRA');
    }

    public function testGenerateOrderCommissionCalculatesRateAndIsIdempotent(): void
    {
        // Asignar 15% de comisión personalizada a la vendedora
        $this->sellerProfileRepo->save(new SellerProfile(userId: 10, commissionRate: 15.00));

        $contract = new ServiceContract(
            clientId: 20,
            planId: 1,
            remainingServices: 5,
            remainingKm: 200.0,
            vehicleId: 5,
            status: ContractStatus::Active,
            id: 50,
            sellerId: 10
        );
        $this->contractRepo->save($contract);

        $order = new Order(
            id: 100,
            uuid: Uuid::generate(),
            orderNumber: 'ORD-2026-0001',
            userId: 20,
            orderableType: OrderableType::ServiceContract,
            orderableId: 50,
            amountUsd: 200.00,
            exchangeRate: 50.00,
            amountVes: 10000.00,
            status: OrderStatus::Paid
        );
        $this->orderRepo->save($order);

        $useCase = new GenerateOrderCommission($this->commissionRepo, $this->sellerProfileRepo);
        $comm = $useCase->execute($order, $contract);

        $this->assertNotNull($comm);
        $this->assertSame(10, $comm->getSellerId());
        $this->assertSame(100, $comm->getOrderId());
        $this->assertSame(30.00, $comm->getAmountUsd()); // 15% de 200.00 USD = 30.00 USD
        $this->assertSame(15.00, $comm->getPercentage());
        $this->assertTrue($comm->getStatus()->isPending());

        // Idempotencia: segunda llamada devuelve la misma comisión sin duplicarla
        $comm2 = $useCase->execute($order, $contract);
        $this->assertSame($comm->getId(), $comm2->getId());
        $this->assertCount(1, $this->commissionRepo->findBySellerId(10));
    }

    public function testGenerateOrderCommissionIgnoresContractsWithoutSeller(): void
    {
        $contractNoSeller = new ServiceContract(
            clientId: 20,
            planId: 1,
            remainingServices: 5,
            remainingKm: 200.0,
            vehicleId: 5,
            status: ContractStatus::Active,
            id: 51,
            sellerId: null
        );

        $order = new Order(
            id: 101,
            uuid: Uuid::generate(),
            orderNumber: 'ORD-2026-0002',
            userId: 20,
            orderableType: OrderableType::ServiceContract,
            orderableId: 51,
            amountUsd: 100.00,
            exchangeRate: 50.00,
            amountVes: 5000.00,
            status: OrderStatus::Paid
        );

        $useCase = new GenerateOrderCommission($this->commissionRepo, $this->sellerProfileRepo);
        $comm = $useCase->execute($order, $contractNoSeller);

        $this->assertNull($comm);
    }

    public function testManageSellerBankAccountValidatesSudebanCode(): void
    {
        $useCase = new ManageSellerBankAccount($this->sellerProfileRepo, $this->planRepo);

        // Banco 1 en InMemoryPlanRepository tiene código '0114' (Bancaribe)
        // Intentar registrar cuenta que empiece por 0102 en Banco 1 debe fallar
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no corresponden al código asignado por Sudeban');

        $useCase->execute(10, [
            'bank_id'             => 1,
            'account_number'      => '01020000112233445566',
            'account_type'        => 'CORRIENTE',
            'account_holder_name' => 'Valeria Vendedora',
            'account_holder_id'   => 'V-20111222',
            'phone_pago_movil'    => '04141112233',
        ]);
    }

    public function testManageSellerBankAccountSavesSuccessfully(): void
    {
        $useCase = new ManageSellerBankAccount($this->sellerProfileRepo, $this->planRepo);

        // Banco 1 es '0114'
        $profile = $useCase->execute(10, [
            'bank_id'             => 1,
            'account_number'      => '01140000112233445566',
            'account_type'        => 'CORRIENTE',
            'account_holder_name' => 'Valeria Vendedora',
            'account_holder_id'   => 'V-20111222',
            'phone_pago_movil'    => '04141112233',
        ]);

        $this->assertTrue($profile->hasCompleteBankDetails());
        $this->assertSame('01140000112233445566', $profile->getAccountNumber());
        $this->assertSame(1, $profile->getBankId());
    }

    public function testRequestWithdrawalFailsWithoutBankDetails(): void
    {
        $useCase = new RequestCommissionWithdrawal($this->commissionRepo, $this->sellerProfileRepo);

        // Sin perfil bancario
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Debe configurar sus datos bancarios');
        $useCase->execute(10);
    }

    public function testRequestWithdrawalSuccess(): void
    {
        // 1. Configurar datos bancarios
        $profile = new SellerProfile(userId: 10, commissionRate: 10.00);
        $profile->updateBankDetails(1, '01020000112233445566', 'CORRIENTE', 'Valeria', 'V-20111222', '04141112233');
        $this->sellerProfileRepo->save($profile);

        // 2. Crear dos comisiones pendientes
        $c1 = new Commission(sellerId: 10, orderId: 101, amountUsd: 15.00, percentage: 10.00, status: CommissionStatus::Pending);
        $c2 = new Commission(sellerId: 10, orderId: 102, amountUsd: 20.00, percentage: 10.00, status: CommissionStatus::Pending);
        $this->commissionRepo->save($c1);
        $this->commissionRepo->save($c2);

        $useCase = new RequestCommissionWithdrawal($this->commissionRepo, $this->sellerProfileRepo);
        $result = $useCase->execute(10);

        $this->assertSame(35.00, $result['requested_amount_usd']);
        $this->assertSame(2, $result['commissions_count']);

        $updated = $this->commissionRepo->findBySellerId(10);
        foreach ($updated as $u) {
            $this->assertTrue($u->getStatus()->isWithdrawalRequested());
        }
    }

    public function testAdminCanUpdateSellerCommissionRateAndPay(): void
    {
        $updateRateUseCase = new UpdateSellerCommissionRate($this->sellerProfileRepo, $this->userRepo);
        $updatedProfile = $updateRateUseCase->execute(10, 18.50);
        $this->assertSame(18.50, $updatedProfile->getCommissionRate());

        // Crear comisión solicitada
        $c = new Commission(sellerId: 10, orderId: 105, amountUsd: 55.50, percentage: 18.50, status: CommissionStatus::WithdrawalRequested);
        $saved = $this->commissionRepo->save($c);

        // Administrador liquida la comisión
        $payUseCase = new PayCommissionByAdmin($this->commissionRepo);
        $paidComm = $payUseCase->execute($saved->getId(), 'TRAN-BANCO-123456');

        $this->assertTrue($paidComm->getStatus()->isPaid());
        $this->assertSame('TRAN-BANCO-123456', $paidComm->getPaymentReference());
        $this->assertNotNull($paidComm->getPaidAt());
    }

    public function testWalletBalancesCalculation(): void
    {
        // Crear varias comisiones con distintos estados
        $this->commissionRepo->save(new Commission(sellerId: 10, orderId: 1, amountUsd: 10.00, percentage: 10.00, status: CommissionStatus::Pending));
        $this->commissionRepo->save(new Commission(sellerId: 10, orderId: 2, amountUsd: 15.00, percentage: 10.00, status: CommissionStatus::WithdrawalRequested));
        $this->commissionRepo->save(new Commission(sellerId: 10, orderId: 3, amountUsd: 25.00, percentage: 10.00, status: CommissionStatus::Paid));
        $this->commissionRepo->save(new Commission(sellerId: 10, orderId: 4, amountUsd: 30.00, percentage: 10.00, status: CommissionStatus::Cancelled));

        $listUseCase = new ListSellerCommissions($this->commissionRepo, $this->sellerProfileRepo, $this->orderRepo, $this->planRepo);
        $result = $listUseCase->execute(10);

        $wallet = $result['wallet'];
        $this->assertSame(50.00, $wallet['total_earned_usd']); // 10 + 15 + 25 (excluye cancelada)
        $this->assertSame(10.00, $wallet['pending_balance_usd']);
        $this->assertSame(15.00, $wallet['withdrawal_requested_usd']);
        $this->assertSame(25.00, $wallet['paid_balance_usd']);
    }

    public function testListAdminCommissionsGlobalSummary(): void
    {
        $this->commissionRepo->save(new Commission(sellerId: 10, orderId: 1, amountUsd: 10.00, percentage: 10.00, status: CommissionStatus::Pending));
        $this->commissionRepo->save(new Commission(sellerId: 11, orderId: 2, amountUsd: 20.00, percentage: 10.00, status: CommissionStatus::Paid));

        $adminUseCase = new ListAdminCommissions($this->commissionRepo, $this->sellerProfileRepo, $this->userRepo, $this->orderRepo);
        $result = $adminUseCase->execute();

        $this->assertSame(30.00, $result['summary']['total_commissions_usd']);
        $this->assertSame(10.00, $result['summary']['total_pending_usd']);
        $this->assertSame(20.00, $result['summary']['total_paid_usd']);
        $this->assertCount(2, $result['commissions']);
    }

    public function testProcessPaymentHookGeneratesSellerCommission(): void
    {
        $this->sellerProfileRepo->save(new SellerProfile(userId: 10, commissionRate: 20.00));

        $contract = new ServiceContract(
            clientId: 20,
            planId: 1,
            remainingServices: 5,
            remainingKm: 200.0,
            vehicleId: 5,
            status: ContractStatus::PendingPayment,
            id: 88,
            sellerId: 10
        );
        $this->contractRepo->save($contract);

        $order = new Order(
            id: 200,
            uuid: Uuid::generate(),
            orderNumber: 'ORD-2026-0099',
            userId: 20,
            orderableType: OrderableType::ServiceContract,
            orderableId: 88,
            amountUsd: 150.00,
            exchangeRate: 50.00,
            amountVes: 7500.00,
            status: OrderStatus::Pending
        );
        $this->orderRepo->save($order);

        $genCommissionUseCase = new GenerateOrderCommission($this->commissionRepo, $this->sellerProfileRepo);

        $processPayment = new ProcessPayment(
            orderRepository: $this->orderRepo,
            paymentRepository: $this->paymentRepo,
            contractRepository: $this->contractRepo,
            planRepository: $this->planRepo,
            paymentGateway: $this->paymentGateway,
            exchangeRateService: $this->exchangeService,
            generateOrderCommission: $genCommissionUseCase
        );

        $result = $processPayment->execute(
            userId: 20,
            orderIdentifier: 200,
            data: [
                'payment_method'       => 'pago_movil',
                'reference'            => 'REF-PAGO-001',
                'identificadorPersona' => 'V18999888',
                'amount'               => 6825.00,
            ]
        );

        $this->assertSame('completed', $result['payment']['status']);

        // Verificar que se haya generado la comisión
        $comm = $this->commissionRepo->findByOrderId(200);
        $this->assertNotNull($comm);
        $this->assertSame(10, $comm->getSellerId());
        $this->assertSame(30.00, $comm->getAmountUsd()); // 20% de 150.00 USD = 30.00 USD
        $this->assertTrue($comm->getStatus()->isPending());
    }

    public function testHandleBancaribeWebhookHookGeneratesSellerCommission(): void
    {
        $this->sellerProfileRepo->save(new SellerProfile(userId: 10, commissionRate: 10.00));

        $contract = new ServiceContract(
            clientId: 20,
            planId: 1,
            remainingServices: 5,
            remainingKm: 200.0,
            vehicleId: 5,
            status: ContractStatus::PendingPayment,
            id: 99,
            sellerId: 10
        );
        $this->contractRepo->save($contract);

        $order = new Order(
            id: 300,
            uuid: Uuid::generate(),
            orderNumber: 'ORD-2026-0300',
            userId: 20,
            orderableType: OrderableType::ServiceContract,
            orderableId: 99,
            amountUsd: 100.00,
            exchangeRate: 50.00,
            amountVes: 5000.00,
            status: OrderStatus::Pending
        );
        $this->orderRepo->save($order);

        $genCommissionUseCase = new GenerateOrderCommission($this->commissionRepo, $this->sellerProfileRepo);
        $webhookSecret = 'secret_webhook_123';

        $webhookHandler = new HandleBancaribeWebhook(
            orderRepository: $this->orderRepo,
            paymentRepository: $this->paymentRepo,
            contractRepository: $this->contractRepo,
            planRepository: $this->planRepo,
            webhookSecret: $webhookSecret,
            generateOrderCommission: $genCommissionUseCase
        );

        $payload = [
            'order_id'       => 300,
            'status'         => 'success',
            'reference'      => 'TX-BANCARIBE-999',
            'transaction_id' => 'TX-BANCARIBE-999',
            'payment_method' => 'pago_movil',
        ];
        $signature = hash_hmac('sha256', json_encode($payload), $webhookSecret);

        $res = $webhookHandler->execute($payload, $signature, json_encode($payload));
        $this->assertSame('paid', $res['status']);

        // Verificar comisión
        $comm = $this->commissionRepo->findByOrderId(300);
        $this->assertNotNull($comm);
        $this->assertSame(10.00, $comm->getAmountUsd()); // 10% de 100.00 = 10.00
    }
}
