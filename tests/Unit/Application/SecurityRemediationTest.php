<?php

namespace Tests\Unit\Application;

use App\Application\Auth\UseCases\ResetPassword;
use App\Application\Contracts\UseCases\GenerateServiceContractPdf;
use App\Application\Payments\UseCases\HandleBancaribeWebhook;
use App\Application\Users\UseCases\UploadUserAvatar;
use App\Domain\Auth\Entities\Role;
use App\Domain\Auth\ValueObjects\Password;
use App\Domain\Contracts\Entities\Plan;
use App\Domain\Contracts\Entities\ServiceContract;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Payments\Entities\Order;
use App\Domain\Payments\Enums\OrderableType;
use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Users\Entities\User;
use App\Domain\Users\ValueObjects\BirthDate;
use App\Domain\Users\ValueObjects\Email;
use App\Domain\Users\ValueObjects\Names;
use App\Domain\Users\ValueObjects\NationalId;
use App\Domain\Users\ValueObjects\Phone;
use App\Domain\Users\ValueObjects\Surnames;
use App\Infrastructure\Database\Redis;
use App\Infrastructure\Pdf\FpdfContractPdfGenerator;
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryDocumentRepository;
use Tests\Doubles\InMemoryImageRepository;
use Tests\Doubles\InMemoryOrderRepository;
use Tests\Doubles\InMemoryPaymentRepository;
use Tests\Doubles\InMemoryPersonalAccessTokenRepository;
use Tests\Doubles\InMemoryPlanRepository;
use Tests\Doubles\InMemorySavedAddressRepository;
use Tests\Doubles\InMemoryServiceContractRepository;
use Tests\Doubles\InMemoryStorageService;
use Tests\Doubles\InMemoryUserRepository;
use Tests\Doubles\InMemoryVehicleCatalogRepository;
use Tests\Doubles\InMemoryVehicleRepository;

class SecurityRemediationTest extends TestCase
{
    private InMemoryUserRepository $userRepo;
    private InMemoryServiceContractRepository $contractRepo;
    private InMemoryPlanRepository $planRepo;
    private InMemoryOrderRepository $orderRepo;
    private InMemoryPaymentRepository $paymentRepo;
    private InMemoryImageRepository $imageRepo;
    private InMemoryStorageService $storageService;
    private Redis $redis;

    protected function setUp(): void
    {
        $this->userRepo = new InMemoryUserRepository();
        $this->contractRepo = new InMemoryServiceContractRepository();
        $this->planRepo = new InMemoryPlanRepository();
        $this->orderRepo = new InMemoryOrderRepository();
        $this->paymentRepo = new InMemoryPaymentRepository();
        $this->imageRepo = new InMemoryImageRepository();
        $this->storageService = new InMemoryStorageService();
        $this->redis = new Redis();
    }

    /**
     * 1. CWE-73 / CWE-59: UploadUserAvatar rechaza rutas fuera de storage o temp
     */
    public function testUploadUserAvatarRejectsArbitraryPathTraversal(): void
    {
        $user = new User(
            id: 1,
            uuid: Uuid::generate(),
            names: new Names('Juan'),
            surnames: new Surnames('Pérez'),
            email: new Email('juan@ubikgo.com'),
            password: Password::fromPlainText('Clave123!'),
            phone: new Phone('+584141112233'),
            walletBalance: new Money(0.00, 'VES'),
            identificationCard: new NationalId('V-20111222'),
            birthDate: new BirthDate('1990-01-01')
        );
        $this->userRepo->save($user);

        $useCase = new UploadUserAvatar($this->userRepo, $this->storageService, $this->imageRepo);

        // Crear o apuntar a un archivo existente fuera de storage (ej: README.md en raíz)
        $sensitiveFile = dirname(__DIR__, 3) . '/README.md';

        $this->expectException(Exception::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage('Acceso denegado: la ruta del archivo no está autorizada para carga de imágenes.');

        $useCase->execute(userId: 1, localFilePath: $sensitiveFile);
    }

    /**
     * 2. BOLA / IDOR: GenerateServiceContractPdf rechaza IDs numéricos directos
     */
    public function testGenerateServiceContractPdfRejectsNumericId(): void
    {
        $contract = new ServiceContract(
            clientId: 1,
            planId: 1,
            remainingServices: 3,
            remainingKm: 80.0,
            vehicleId: 1,
            status: ContractStatus::Active,
            id: 100
        );
        $this->contractRepo->save($contract);

        $pdfUseCase = new GenerateServiceContractPdf(
            $this->contractRepo,
            $this->planRepo,
            $this->userRepo,
            new InMemoryVehicleRepository(),
            new InMemorySavedAddressRepository(),
            new InMemoryVehicleCatalogRepository(),
            new FpdfContractPdfGenerator()
        );

        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('El contrato solicitado no existe.');

        // Intentar enumerar el contrato número 100
        $pdfUseCase->execute('100');
    }

    /**
     * 3. OWASP API8:2023: Webhook exige secreto y valida HMAC-SHA256
     */
    public function testBancaribeWebhookValidatesHmacSha256AndRejectsTamperedBody(): void
    {
        $plan = $this->planRepo->save(new Plan(name: 'Plan Express', priceUsd: 50.00));
        $contract = $this->contractRepo->save(new ServiceContract(
            clientId: 5,
            planId: $plan->getId(),
            remainingServices: 3,
            remainingKm: 80.00,
            status: ContractStatus::PendingPayment
        ));
        $order = $this->orderRepo->save(new Order(
            userId: 5,
            orderableType: OrderableType::ServiceContract,
            orderableId: $contract->getId(),
            amountUsd: 50.00,
            exchangeRate: 45.00,
            status: OrderStatus::Pending
        ));

        $secret = 'webhook-hmac-secret-test';
        $webhook = new HandleBancaribeWebhook(
            $this->orderRepo,
            $this->paymentRepo,
            $this->contractRepo,
            $this->planRepo,
            $secret
        );

        $payload = [
            'order_number' => $order->getOrderNumber(),
            'reference'    => 'REF998877',
            'status'       => 'APPROVED',
        ];
        $rawBody = json_encode($payload);
        $validHmac = hash_hmac('sha256', $rawBody, $secret);

        // 1. Con HMAC válido se aprueba
        $res = $webhook->execute($payload, $validHmac, $rawBody);
        $this->assertSame('paid', $res['status']);

        // 2. Con HMAC alterado se rechaza con 401
        $this->expectException(Exception::class);
        $this->expectExceptionCode(401);
        $webhook->execute($payload, 'invalid-tampered-hmac', $rawBody);
    }

    /**
     * 4. OWASP API8:2023: Webhook falla con 500 si no hay secreto configurado
     */
    public function testBancaribeWebhookRejectsWhenNoSecretConfigured(): void
    {
        $prevEnv = $_ENV['BANCARIBE_WEBHOOK_SECRET'] ?? null;
        unset($_ENV['BANCARIBE_WEBHOOK_SECRET']);

        $webhook = new HandleBancaribeWebhook(
            $this->orderRepo,
            $this->paymentRepo,
            $this->contractRepo,
            $this->planRepo,
            null
        );

        $this->expectException(Exception::class);
        $this->expectExceptionCode(500);

        try {
            $webhook->execute(['order_number' => 'ORD-1', 'reference' => '123', 'status' => 'APPROVED']);
        } finally {
            if ($prevEnv !== null) {
                $_ENV['BANCARIBE_WEBHOOK_SECRET'] = $prevEnv;
            }
        }
    }

    /**
     * 6. Fuerza Bruta OTP: Se destruye el código tras 5 intentos fallidos
     */
    public function testOtpResetPasswordInvalidatesAfter5FailedAttempts(): void
    {
        $user = new User(
            id: null,
            uuid: Uuid::generate(),
            names: new Names('Test'),
            surnames: new Surnames('User'),
            email: new Email('bruteforce@ubikgo.com'),
            password: Password::fromPlainText('OldPassword123!'),
            phone: new Phone('+584149991122'),
            walletBalance: new Money(0.00, 'VES'),
            identificationCard: new NationalId('V19999888'),
            birthDate: new BirthDate('1995-05-15'),
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            consentAt: new DateTimeImmutable(),
            deletedAt: null,
            isActive: true,
            roles: [new Role(1, 'client', 'api')],
            permissions: []
        );
        $this->userRepo->save($user);

        $tokenRepo = new InMemoryPersonalAccessTokenRepository($this->userRepo);
        $resetUseCase = new ResetPassword($this->userRepo, $tokenRepo, $this->redis);

        $redisKey = 'password_reset:' . md5('bruteforce@ubikgo.com');
        $attemptsKey = 'password_reset_attempts:' . md5('bruteforce@ubikgo.com');

        $this->redis->execute('SET', $redisKey, '555666');
        $this->redis->execute('DEL', $attemptsKey);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            try {
                $resetUseCase->execute('bruteforce@ubikgo.com', '11111' . $attempt, 'NewPass123!', 'NewPass123!');
                $this->fail("Debió fallar intento {$attempt}");
            } catch (Exception $e) {
                $this->assertSame(400, $e->getCode());
            }
        }

        // Intento 5: debe bloquear con 429 e invalidar el código
        try {
            $resetUseCase->execute('bruteforce@ubikgo.com', '111115', 'NewPass123!', 'NewPass123!');
            $this->fail("Debió fallar intento 5 con 429");
        } catch (Exception $e) {
            $this->assertSame(429, $e->getCode());
        }

        // Verificar que el código fue borrado de Redis
        $this->assertNull($this->redis->execute('GET', $redisKey));
    }
}
