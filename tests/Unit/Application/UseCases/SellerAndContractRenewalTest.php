<?php

namespace Tests\Unit\Application\UseCases;

use App\Application\Auth\UseCases\ReviewKycDocument;
use App\Application\Contracts\UseCases\{
    CheckoutServiceContract,
    ListSellerContracts,
    RedeemServiceContract,
    RenewServiceContract
};
use App\Application\Payments\UseCases\ProcessPayment;
use App\Application\Users\UseCases\{
    RegisterClientBySeller,
    SearchClientByNationalId
};
use App\Application\Vehicles\UseCases\RegisterVehicle;
use App\Domain\Auth\{
    Entities\Document,
    Enums\DocumentStatus,
    Enums\DocumentType
};
use App\Domain\Contracts\{
    Entities\Plan,
    Enums\ContractStatus
};
use App\Domain\Shared\Services\MailerInterface;
use Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Doubles\{
    FakeDocumentExtractor,
    FakeExchangeRateService,
    FakePaymentGateway,
    InMemoryDocumentRepository,
    InMemoryMailer,
    InMemoryOrderRepository,
    InMemoryPaymentRepository,
    InMemoryPlanRepository,
    InMemoryServiceContractRepository,
    InMemoryUserRepository,
    InMemoryVehicleRepository
};

class SellerAndContractRenewalTest extends TestCase
{
    private InMemoryUserRepository $userRepo;
    private InMemoryVehicleRepository $vehicleRepo;
    private InMemoryDocumentRepository $docRepo;
    private InMemoryPlanRepository $planRepo;
    private InMemoryServiceContractRepository $contractRepo;
    private InMemoryOrderRepository $orderRepo;
    private InMemoryPaymentRepository $paymentRepo;
    private FakeExchangeRateService $exchangeService;
    private FakePaymentGateway $paymentGateway;
    private FakeDocumentExtractor $docExtractor;
    private InMemoryMailer $mailer;

    protected function setUp(): void
    {
        $this->userRepo = new InMemoryUserRepository();
        $this->vehicleRepo = new InMemoryVehicleRepository();
        $this->docRepo = new InMemoryDocumentRepository();
        $this->planRepo = new InMemoryPlanRepository();
        $this->contractRepo = new InMemoryServiceContractRepository();
        $this->orderRepo = new InMemoryOrderRepository();
        $this->paymentRepo = new InMemoryPaymentRepository();
        $this->exchangeService = new FakeExchangeRateService(50.00);
        $this->paymentGateway = new FakePaymentGateway();
        $this->docExtractor = new FakeDocumentExtractor();
        $this->mailer = new InMemoryMailer();

        $this->planRepo->save(new Plan(
            name: 'Plan Gold Vial',
            priceUsd: 60.00,
            durationMonths: 12,
            maxServices: 3,
            maxKm: 100.0,
            description: 'Cobertura anual de 3 grúas y 100 km',
            id: 1
        ));
    }

    public function testSellerRegistersClientAutoVerifiesIdentityAndSendsWelcomeEmail(): void
    {
        $registerBySeller = new RegisterClientBySeller(
            $this->userRepo,
            $this->docExtractor,
            $this->docRepo,
            $this->mailer
        );

        $result = $registerBySeller->execute(
            rawEmail: 'nuevo.cliente@ubikgo.com',
            rawPhone: '04141112233',
            documentFilePath: null,
            rawNationalId: 'V-20111222',
            rawNames: 'María Fernanda',
            rawSurnames: 'Gómez Rivas',
            rawBirthDate: '1994-06-15',
            sellerName: 'Valentina Ventas'
        );

        $this->assertSame('VERIFIED', $result['kyc_identity_status']);
        $this->assertTrue($result['email_sent']);
        $this->assertSame('V-20111222', $result['client']['identification_card']);

        // Verificar que la cédula quedó VERIFIED sin requerir revisión de administrador
        $clientId = 1;
        $this->assertTrue($this->docRepo->isUserIdentityVerified($clientId));

        // Verificar que el correo HTML corporativo fue enviado con la clave generada
        $this->assertSame(1, $this->mailer->getSentCount());
        $sentBody = $this->mailer->getLastSent()['htmlBody'] ?? '';
        $this->assertStringContainsString('nuevo.cliente@ubikgo.com', $sentBody);
        $this->assertStringContainsString('#4E4293', $sentBody);
    }

    public function testSellerRegistrationSucceedsSilentlyWhenSmtpServerFails(): void
    {
        $failingMailer = new class implements MailerInterface {
            public function send(string $to, string $subject, string $htmlBody): bool
            {
                throw new RuntimeException('SMTP connection refused');
            }
        };

        $registerBySeller = new RegisterClientBySeller(
            $this->userRepo,
            $this->docExtractor,
            $this->docRepo,
            $failingMailer
        );

        $result = $registerBySeller->execute(
            rawEmail: 'sin.smtp@ubikgo.com',
            rawPhone: '04129998877',
            documentFilePath: null,
            rawNationalId: 'V-22333444',
            rawNames: 'Pedro José',
            rawSurnames: 'Castillo'
        );

        $this->assertFalse($result['email_sent']);
        $this->assertSame('V-22333444', $result['client']['identification_card']);
    }

    public function testSellerSearchesClientByNationalIdAndRegistersVerifiedVehicle(): void
    {
        $registerBySeller = new RegisterClientBySeller(
            $this->userRepo,
            $this->docExtractor,
            $this->docRepo,
            $this->mailer
        );
        $registerBySeller->execute(
            rawEmail: 'busqueda@ubikgo.com',
            rawPhone: '04145556677',
            documentFilePath: null,
            rawNationalId: 'V-19888777',
            rawNames: 'Luis Alberto',
            rawSurnames: 'Mendoza'
        );

        // Registrar vehículo por vendedora (autoVerifyBySeller = true)
        $registerVehicle = new RegisterVehicle($this->vehicleRepo, $this->userRepo, $this->docRepo);
        $vehicleDto = $registerVehicle->execute(
            userId: 1,
            rawPlate: 'AA111BB',
            rawNiv: '8Z1ABCDE987654321',
            year: 2021,
            color: 'AZUL',
            brandName: 'TOYOTA',
            modelName: 'YARIS',
            autoVerifyBySeller: true
        );

        $this->assertTrue($vehicleDto['is_verified']);
        $this->assertSame('ACTIVE', $vehicleDto['status']);

        // Buscar cliente por cédula
        $searchUseCase = new SearchClientByNationalId(
            $this->userRepo,
            $this->vehicleRepo,
            $this->contractRepo,
            $this->planRepo
        );

        $found = $searchUseCase->execute('V-19888777');
        $this->assertTrue($found['found']);
        $this->assertCount(1, $found['vehicles']);
        $this->assertSame('AA111BB', $found['vehicles'][0]['plate_number']);

        $notFound = $searchUseCase->execute('V-99999999');
        $this->assertFalse($notFound['found']);
    }

    public function testAdminReviewsKycDocumentAndAutomaticallyVerifiesVehicle(): void
    {
        $registerBySeller = new RegisterClientBySeller(
            $this->userRepo,
            $this->docExtractor,
            $this->docRepo,
            $this->mailer
        );
        $registerBySeller->execute(
            rawEmail: 'autogestion@ubikgo.com',
            rawPhone: '04147778899',
            documentFilePath: null,
            rawNationalId: 'V-15222333',
            rawNames: 'Ana Karina',
            rawSurnames: 'Lugo'
        );

        // Cliente autogestionado registra vehículo (queda en PENDING, is_verified = false)
        $registerVehicle = new RegisterVehicle($this->vehicleRepo, $this->userRepo, $this->docRepo);
        $vehicleDto = $registerVehicle->execute(
            userId: 1,
            rawPlate: 'CC222DD',
            rawNiv: '8Z1ABCDE111222333',
            year: 2020,
            color: 'NEGRO',
            autoVerifyBySeller: false
        );
        $this->assertFalse($vehicleDto['is_verified']);

        // Crear documento vehicular PENDING
        $doc = Document::create(
            documentableType: 'vehicle',
            documentableId: 1,
            documentType: DocumentType::VehicleRegistration,
            filePath: 'storage/documents/carnet_test.jpg',
            extractedData: ['plate_number' => 'CC222DD'],
            status: DocumentStatus::Pending
        );
        $this->docRepo->save($doc);

        $reviewUseCase = new ReviewKycDocument($this->docRepo, $this->vehicleRepo);
        $pendingList = $reviewUseCase->listByStatus('PENDING');
        $this->assertCount(1, $pendingList);

        $reviewed = $reviewUseCase->execute(
            documentId: (int)$doc->getId(),
            rawDecision: 'VERIFIED',
            reason: 'Carnet legible y coincidente con INTT',
            adminUserId: 99
        );

        $this->assertSame('VERIFIED', $reviewed['status']);
        $this->assertTrue($reviewed['vehicle_verified']);
        $this->assertTrue($this->vehicleRepo->findById(1)->isVerified());
    }

    public function testSellerCannotPayViaC2pButCanPayViaPagoMovilAndRenewExpiredContract(): void
    {
        // 1. Vendedora (ID 50) registra cliente (ID 1) y su vehículo (ID 1)
        $registerBySeller = new RegisterClientBySeller($this->userRepo, $this->docExtractor, $this->docRepo, $this->mailer);
        $registerBySeller->execute(
            rawEmail: 'cliente.contrato@ubikgo.com',
            rawPhone: '04143334455',
            documentFilePath: null,
            rawNationalId: 'V-17654321',
            rawNames: 'Roberto',
            rawSurnames: 'Salazar'
        );

        $registerVehicle = new RegisterVehicle($this->vehicleRepo, $this->userRepo, $this->docRepo);
        $registerVehicle->execute(
            userId: 1,
            rawPlate: 'XY987ZT',
            rawNiv: '8Z1ABCDE555666777',
            year: 2023,
            color: 'GRIS',
            autoVerifyBySeller: true
        );

        // 2. Vendedora (sellerId = 50) crea el contrato en PENDING_PAYMENT
        $checkout = new CheckoutServiceContract(
            $this->planRepo,
            $this->vehicleRepo,
            $this->contractRepo,
            $this->orderRepo,
            $this->exchangeService
        );
        $checkoutResult = $checkout->execute(
            userId: 1,
            planIdentifier: 1,
            vehicleIdentifier: 1,
            sellerId: 50
        );

        $this->assertSame(50, $checkoutResult['contract']['seller_id']);
        $this->assertSame(['pago_movil'], $checkoutResult['payment_instructions']['supported_methods']);
        $orderNumber = $checkoutResult['order']['order_number'];
        $contractUuid = $checkoutResult['contract']['id'];

        $processPayment = new ProcessPayment(
            $this->orderRepo,
            $this->paymentRepo,
            $this->contractRepo,
            $this->planRepo,
            $this->paymentGateway,
            $this->exchangeService
        );

        // 3. Si la vendedora intenta pagar por C2P, debe ser rechazado con 422
        try {
            $processPayment->execute(
                userId: 50,
                orderIdentifier: $orderNumber,
                data: [
                    'payment_method'  => 'c2p',
                    'bancoPagador'    => '0102',
                    'documentType'    => 'V',
                    'documentNumber'  => '17654321',
                    'telefonoPersona' => '04143334455',
                    'otp'             => '12345678',
                ],
                isSeller: true
            );
            $this->fail('Se esperaba excepción al intentar pagar por C2P como vendedora.');
        } catch (Exception $e) {
            $this->assertSame(422, $e->getCode());
            $this->assertStringContainsString('Pago Móvil', $e->getMessage());
        }

        // 4. La vendedora registra el pago por Pago Móvil (Operaciones Plus) -> Activa el contrato
        $payResult = $processPayment->execute(
            userId: 50,
            orderIdentifier: $orderNumber,
            data: [
                'payment_method'       => 'pago_movil',
                'reference'            => 'REF1002001',
                'date'                 => date('Y-m-d'),
                'amount'               => 3000.00,
                'identificadorPersona' => 'V17654321',
            ],
            isSeller: true
        );

        $this->assertSame('ACTIVE', $payResult['contract']['status']);
        $this->assertNotNull($payResult['contract']['starts_at']);
        $this->assertNotNull($payResult['contract']['expires_at']);
        $this->assertSame(3, $payResult['contract']['remaining_services']);
        $this->assertSame(100.0, $payResult['contract']['remaining_km']);

        // 5. El cliente canjea un servicio de grúa de 40 km
        $redeemUseCase = new RedeemServiceContract($this->contractRepo, $this->planRepo, $this->vehicleRepo);
        $redeemed = $redeemUseCase->execute(
            contractIdentifier: $contractUuid,
            actorUserId: 1,
            distanceKm: 40.0
        );
        $this->assertSame(2, $redeemed['contract']['remaining_services']);
        $this->assertSame(60.0, $redeemed['contract']['remaining_km']);

        // 6. Simular vencimiento del contrato tras el año y verificar listado de la vendedora
        $contractEntity = $this->contractRepo->findById(1);
        $contractEntity->expire();
        $this->contractRepo->save($contractEntity);

        $listSellerContracts = new ListSellerContracts(
            $this->contractRepo,
            $this->planRepo,
            $this->userRepo,
            $this->vehicleRepo,
            $this->orderRepo
        );
        $expiredContracts = $listSellerContracts->execute(50, 'EXPIRED');
        $this->assertCount(1, $expiredContracts);
        $this->assertSame(ContractStatus::Expired->getValue(), $expiredContracts[0]['status']);

        // 7. Renovar el contrato vencido -> Genera una NUEVA orden (1 orden por año de contrato)
        $renewUseCase = new RenewServiceContract(
            $this->contractRepo,
            $this->planRepo,
            $this->vehicleRepo,
            $this->orderRepo,
            $this->exchangeService
        );
        $renewalData = $renewUseCase->execute(
            contractIdentifier: $contractUuid,
            actorUserId: 50,
            isSeller: true
        );

        $this->assertTrue($renewalData['is_renewal']);
        $renewalOrderNumber = $renewalData['order']['order_number'];
        $this->assertNotSame($orderNumber, $renewalOrderNumber);

        // 8. Pagar la nueva orden de renovación -> El contrato vuelve a ACTIVE y restaura sus 3 servicios y 100 km
        $renewPayResult = $processPayment->execute(
            userId: 50,
            orderIdentifier: $renewalOrderNumber,
            data: [
                'payment_method'       => 'pago_movil',
                'reference'            => 'REF2003002',
                'date'                 => date('Y-m-d'),
                'amount'               => 3000.00,
                'identificadorPersona' => 'V17654321',
            ],
            isSeller: true
        );

        $this->assertSame('ACTIVE', $renewPayResult['contract']['status']);
        $this->assertSame(3, $renewPayResult['contract']['remaining_services']);
        $this->assertSame(100.0, $renewPayResult['contract']['remaining_km']);
    }
}
