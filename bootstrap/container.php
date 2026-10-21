<?php
// bootstrap/container.php

use App\Application\Contracts\UseCases\CheckoutServiceContract;
use App\Application\Documents\UseCases\EnqueueDocumentS3Upload;
use App\Application\Documents\UseCases\ProcessDocumentS3UploadJob;
use App\Application\Driver\UseCases\GetDriverFleetStatus;
use App\Application\Driver\UseCases\RegisterDriverCrane;
use App\Application\Driver\UseCases\UploadDriverLicense;
use App\Application\Users\UseCases\CreateSavedAddress;
use App\Application\Users\UseCases\DeleteSavedAddress;
use App\Application\Vehicles\UseCases\DeleteVehicle;
use App\Application\Auth\UseCases\GetKycStatus;
use App\Application\Payments\UseCases\HandleBancaribeWebhook;
use App\Application\Contracts\UseCases\ListPlans;
use App\Application\Users\UseCases\ListSavedAddresses;
use App\Application\Contracts\UseCases\ListUserContractsAndOrders;
use App\Application\Vehicles\UseCases\ListUserVehicles;
use App\Application\Auth\UseCases\LoginUser;
use App\Application\Auth\UseCases\LogoutUser;
use App\Application\Contracts\UseCases\ManagePlan;
use App\Application\Contracts\UseCases\GenerateServiceContractPdf;
use App\Application\Payments\UseCases\ProcessPayment;
use App\Application\Auth\UseCases\RefreshToken;
use App\Application\Auth\UseCases\RegisterUser;
use App\Application\Vehicles\UseCases\RegisterVehicle;
use App\Application\Auth\UseCases\ResetPassword;
use App\Application\Auth\UseCases\ScanIdentityCard;
use App\Application\Vehicles\UseCases\ScanVehicleRegistration;
use App\Application\Auth\UseCases\SendPasswordResetCode;
use App\Application\Auth\UseCases\UploadKycDocument;
use App\Controllers\AuthController;
use App\Controllers\DocumentController;
use App\Controllers\DriverController;
use App\Controllers\HomeController;
use App\Controllers\PaymentController;
use App\Controllers\PlanController;
use App\Controllers\SavedAddressController;
use App\Controllers\VehicleController;
use App\Core\JwtService;
use App\Domain\Shared\Services\QueueServiceInterface;
use App\Domain\Shared\Services\StorageServiceInterface;
use App\Domain\Vehicles\Services\VehicleMatcherService;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Redis;
use App\Infrastructure\Logging\IntegrationLogger;
use App\Infrastructure\Queue\RedisQueueService;
use App\Infrastructure\Storage\AwsS3StorageService;
use App\Infrastructure\Database\Repositories\Contracts\CachedPlanRepository;
use App\Infrastructure\Database\Repositories\Vehicles\CachedVehicleCatalogRepository;
use App\Infrastructure\Database\Repositories\Payments\MariaDbBankTokenRepository;
use App\Infrastructure\Database\Repositories\Auth\MariaDbDocumentRepository;
use App\Infrastructure\Database\Repositories\Payments\MariaDbExchangeRateRepository;
use App\Infrastructure\Database\Repositories\Payments\MariaDbOrderRepository;
use App\Infrastructure\Database\Repositories\Payments\MariaDbPaymentRepository;
use App\Infrastructure\Database\Repositories\Auth\MariaDbPersonalAccessTokenRepository;
use App\Infrastructure\Database\Repositories\Contracts\MariaDbPlanRepository;
use App\Infrastructure\Database\Repositories\Users\MariaDbSavedAddressRepository;
use App\Infrastructure\Database\Repositories\Contracts\MariaDbServiceContractRepository;
use App\Infrastructure\Database\Repositories\Users\MariaDbUserRepository;
use App\Infrastructure\Database\Repositories\Vehicles\MariaDbVehicleCatalogRepository;
use App\Infrastructure\Database\Repositories\Vehicles\MariaDbVehicleRepository;
use App\Infrastructure\Http\CurlHttpClient;
use App\Infrastructure\Mail\SmtpMailer;
use App\Infrastructure\Services\Bancaribe\BancaribeAuthService;
use App\Infrastructure\Services\Bancaribe\BancaribeBalanceService;
use App\Infrastructure\Services\Bancaribe\BancaribeExchangeService;
use App\Infrastructure\Services\Bancaribe\BancaribePaymentService;
use App\Infrastructure\Services\LaVenezolanaDocumentExtractor;
use App\Infrastructure\Services\RedisAccountRateLimiter;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\AuthorizeMiddleware;
use App\Middlewares\CorsMiddleware;
use App\Middlewares\EnforceJsonResponseMiddleware;
use App\Middlewares\EnsureKycVerifiedMiddleware;
use App\Middlewares\ThrottleMiddleware;
use App\Infrastructure\Pdf\FpdfContractPdfGenerator;
use App\Application\Contracts\UseCases\CancelServiceContract;

// Conexiones con MariaDB y Redis
// instanciadas de manera lazy para no abrir conexiones innecesarias en rutas públicas
$getDb = function () {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = (new Database())->getConnection();
    }
    return $pdo;
};

$getRedis = function () {
    static $redis = null;
    if ($redis === null) {
        $redis = new Redis();
    }
    return $redis;
};

// Servicios base compartidos
$jwtService = new JwtService();

$httpClient = new CurlHttpClient(
    timeout: (int) ($_ENV['HTTP_TIMEOUT'] ?? 30),
    connectTimeout: (int) ($_ENV['HTTP_CONNECT_TIMEOUT'] ?? 5)
);

// Servicio OCR de extracción de documentos (La Venezolana Document Extraction API)
$documentExtractor = new LaVenezolanaDocumentExtractor(
    httpClient: $httpClient,
    baseUrl: $_ENV['OCR_BASE_URL'] ?? 'http://localhost:8080/api/v1',
    apiKey: !empty($_ENV['OCR_API_KEY']) ? $_ENV['OCR_API_KEY'] : null
);

// Servicio Mailer
$mailer = new SmtpMailer(
    host: $_ENV['SMTP_HOST'] ?? 'localhost',
    port: (int)($_ENV['SMTP_PORT'] ?? 465),
    username: $_ENV['SMTP_USERNAME'] ?? '',
    password: $_ENV['SMTP_PASSWORD'] ?? '',
    fromAddress: $_ENV['MAIL_FROM_ADDRESS'] ?? 'soporte@ubikgo.com',
    fromName: $_ENV['MAIL_FROM_NAME'] ?? 'UbikGo',
    encryption: $_ENV['SMTP_ENCRYPTION'] ?? 'ssl'
);

$integrationLogger = new IntegrationLogger();

// Repositorios Lazy
$getUserRepo = fn() => new MariaDbUserRepository($getDb());
$getTokenRepo = fn() => new MariaDbPersonalAccessTokenRepository($getDb());
$getRateLimiter = fn() => new RedisAccountRateLimiter($getRedis());
$getDocumentRepo = fn() => new MariaDbDocumentRepository($getDb());
$getSavedAddressRepo = fn() => new MariaDbSavedAddressRepository($getDb());
$getVehicleRepo = fn() => new MariaDbVehicleRepository($getDb());
$getCatalogRepo = fn() => new CachedVehicleCatalogRepository(
    new MariaDbVehicleCatalogRepository($getDb()),
    $getRedis()
);

// Repositorios y Servicios Bancarios / Planes (Dominio 2 y 5)
$getBankTokenRepo = fn() => new MariaDbBankTokenRepository($getDb());
$getExchangeRateRepo = fn() => new MariaDbExchangeRateRepository($getDb());
$getPlanRepo = fn() => new CachedPlanRepository(
    new MariaDbPlanRepository($getDb()),
    $getRedis()
);
$getContractRepo = fn() => new MariaDbServiceContractRepository($getDb());
$getOrderRepo = fn() => new MariaDbOrderRepository($getDb());
$getPaymentRepo = fn() => new MariaDbPaymentRepository($getDb());

$isSandbox = filter_var($_ENV['BANCARIBE_SANDBOX'] ?? false, FILTER_VALIDATE_BOOLEAN);
$isLocalEnv = ($_ENV['APP_ENV'] ?? 'local') === 'local';

$getBancaribeAuth = fn() => new BancaribeAuthService(
    httpClient: $httpClient,
    tokenRepository: $getBankTokenRepo(),
    redis: $getRedis(),
    baseAuthUrl: $_ENV['BANCARIBE_BASE_AUTH_URL'] ?? '',
    oauth2Endpoint: $_ENV['BANCARIBE_OAUTH2_ENDPOINT'] ?? 'oauth2/token',
    customerKey: $_ENV['BANCARIBE_COSTUMER_KEY'] ?? '',
    customerSecret: $_ENV['BANCARIBE_COSTUMER_SECRET'] ?? '',
    sandboxMode: $isSandbox
);

$getExchangeService = fn() => new BancaribeExchangeService(
    authService: $getBancaribeAuth(),
    httpClient: $httpClient,
    rateRepository: $getExchangeRateRepo(),
    redis: $getRedis(),
    baseUrl: $_ENV['BANCARIBE_BASE_URL'] ?? '',
    exchangeEndpoint: $_ENV['BANCARIBE_EXCHANGE_ENDPOINT'] ?? 'BCVMicro/1.0.0/consultaTasa/tasa',
    exchangeHash: $_ENV['BANCARIBE_EXCHANGE_HASH'] ?? '',
    rif: $_ENV['APP_RIF'] ?? 'J502098500',
    isLocalEnv: $isLocalEnv,
    fallbackRate: (float)($_ENV['BANCARIBE_FALLBACK_RATE'] ?? 40.00)
);

$getPaymentGateway = fn() => new BancaribePaymentService(
    authService: $getBancaribeAuth(),
    exchangeService: $getExchangeService(),
    httpClient: $httpClient,
    baseUrl: $_ENV['BANCARIBE_BASE_URL'] ?? '',
    c2pEndpoint: $_ENV['BANCARIBE_C2P_ENDPOINT'] ?? 'C2P/1.0.0/registrarPagoC2pApi',
    plusEndpoint: $_ENV['BANCARIBE_PLUS_ENDPOINT'] ?? 'COP/1.0.0/consulta-operacion',
    c2pHash: $_ENV['BANCARIBE_C2P_HASH'] ?? '',
    plusHash: $_ENV['BANCARIBE_PLUS_HASH'] ?? '',
    rifComercio: $_ENV['APP_RIF'] ?? 'J502098500',
    telefonoComercio: $_ENV['APP_TELEFONO'] ?? '04267536320',
    nombreComercio: $_ENV['APP_NAME'] ?? 'UbikGo',
    sandboxMode: $isSandbox
);

$getBalanceGateway = fn() => new BancaribeBalanceService(
    authService: $getBancaribeAuth(),
    httpClient: $httpClient,
    redis: $getRedis(),
    baseUrl: $_ENV['BANCARIBE_BASE_URL'] ?? '',
    checkBalanceEndpoint: $_ENV['BANCARIBE_CHECK_BALANCE_ENDPOINT'] ?? 'Cuentas/ConsultaSaldo/1.0.0/ctasConsultaSaldos',
    checkBalanceHash: $_ENV['BANCARIBE_CHECK_BALANCE_HASH'] ?? '',
    rif: $_ENV['APP_RIF'] ?? 'J502098500',
    sandboxMode: $isSandbox
);

// Servicios de Almacenamiento S3 y Colas en Redis (NFR-20-B)
$getQueueService = fn() => new RedisQueueService($getRedis());
$getStorageService = fn() => new AwsS3StorageService(logger: $integrationLogger);
$getEnqueueS3 = fn() => new EnqueueDocumentS3Upload($getQueueService());
$getProcessS3 = fn() => new ProcessDocumentS3UploadJob(
    queueService: $getQueueService(),
    storageService: $getStorageService(),
    documentRepository: $getDocumentRepo(),
    logger: $integrationLogger
);

return [
    'controllers' => [
        AuthController::class => fn() => new AuthController(
            new LoginUser($getUserRepo(), $getTokenRepo(), $jwtService, $getRateLimiter()),
            new RegisterUser($getUserRepo(), $documentExtractor, $jwtService, $getTokenRepo(), $getDocumentRepo(), $getEnqueueS3()),
            new RefreshToken($getTokenRepo(), $getUserRepo(), $jwtService),
            new LogoutUser($getTokenRepo()),
            new SendPasswordResetCode($getUserRepo(), $mailer, $getRedis()),
            new ResetPassword($getUserRepo(), $getTokenRepo(), $getRedis()),
            new ScanIdentityCard($documentExtractor)
        ),
        HomeController::class => fn() => new HomeController(),
        DocumentController::class => fn() => new DocumentController(
            new UploadKycDocument($getDocumentRepo(), $getUserRepo(), $documentExtractor, $getEnqueueS3()),
            new GetKycStatus($getDocumentRepo(), $getUserRepo()),
            new \App\Application\Auth\UseCases\ReviewKycDocument($getDocumentRepo(), $getVehicleRepo())
        ),
        SavedAddressController::class => fn() => new SavedAddressController(
            new CreateSavedAddress($getSavedAddressRepo(), $getUserRepo()),
            new ListSavedAddresses($getSavedAddressRepo(), $getUserRepo()),
            new DeleteSavedAddress($getSavedAddressRepo())
        ),
        VehicleController::class => fn() => new VehicleController(
            new RegisterVehicle($getVehicleRepo(), $getUserRepo(), $getDocumentRepo(), $getCatalogRepo(), $getEnqueueS3()),
            new ListUserVehicles($getVehicleRepo(), $getUserRepo()),
            new DeleteVehicle($getVehicleRepo()),
            new ScanVehicleRegistration($documentExtractor, new VehicleMatcherService($getCatalogRepo())),
            $getCatalogRepo()
        ),
        PlanController::class => fn() => new PlanController(
            new ListPlans($getPlanRepo(), $getExchangeService()),
            new ManagePlan($getPlanRepo(), $getExchangeService()),
            $getPlanRepo()
        ),
        PaymentController::class => fn() => new PaymentController(
            new CheckoutServiceContract(
                $getPlanRepo(),
                $getVehicleRepo(),
                $getContractRepo(),
                $getOrderRepo(),
                $getExchangeService(),
                $_ENV['APP_RIF'] ?? 'J502098500',
                $_ENV['APP_TELEFONO'] ?? '04267536320',
                $_ENV['APP_NAME'] ?? 'UbikGo'
            ),
            new ProcessPayment(
                $getOrderRepo(),
                $getPaymentRepo(),
                $getContractRepo(),
                $getPlanRepo(),
                $getPaymentGateway(),
                $getExchangeService()
            ),
            new HandleBancaribeWebhook(
                $getOrderRepo(),
                $getPaymentRepo(),
                $getContractRepo(),
                $getPlanRepo(),
                !empty($_ENV['BANCARIBE_WEBHOOK_SECRET']) ? $_ENV['BANCARIBE_WEBHOOK_SECRET'] : null
            ),
            new ListUserContractsAndOrders(
                $getContractRepo(),
                $getOrderRepo(),
                $getPlanRepo(),
                $getVehicleRepo(),
                $getExchangeService()
            ),
            $getExchangeService(),
            $getBalanceGateway(),
            new GenerateServiceContractPdf(
                $getContractRepo(),
                $getPlanRepo(),
                $getUserRepo(),
                $getVehicleRepo(),
                $getSavedAddressRepo(),
                $getCatalogRepo(),
                new FpdfContractPdfGenerator()
            ),
            new CancelServiceContract(
                $getContractRepo(),
                $getPlanRepo(),
                $getVehicleRepo()
            ),
            new \App\Application\Contracts\UseCases\ExpireOverdueContracts(
                $getContractRepo()
            ),
            new \App\Application\Contracts\UseCases\RenewServiceContract(
                $getContractRepo(),
                $getPlanRepo(),
                $getVehicleRepo(),
                $getOrderRepo(),
                $getExchangeService(),
                $_ENV['APP_RIF'] ?? 'J502098500',
                $_ENV['APP_TELEFONO'] ?? '04267536320',
                $_ENV['APP_NAME'] ?? 'UbikGo'
            ),
            new \App\Application\Contracts\UseCases\RedeemServiceContract(
                $getContractRepo(),
                $getPlanRepo(),
                $getVehicleRepo()
            )
        ),
        \App\Controllers\SellerController::class => fn() => new \App\Controllers\SellerController(
            new \App\Application\Users\UseCases\SearchClientByNationalId(
                $getUserRepo(),
                $getVehicleRepo(),
                $getContractRepo(),
                $getPlanRepo(),
                $getRedis()
            ),
            new \App\Application\Users\UseCases\RegisterClientBySeller(
                $getUserRepo(),
                $documentExtractor,
                $getDocumentRepo(),
                $mailer,
                $getRedis(),
                $getEnqueueS3()
            ),
            new RegisterVehicle($getVehicleRepo(), $getUserRepo(), $getDocumentRepo(), $getCatalogRepo(), $getEnqueueS3()),
            new CheckoutServiceContract(
                $getPlanRepo(),
                $getVehicleRepo(),
                $getContractRepo(),
                $getOrderRepo(),
                $getExchangeService(),
                $_ENV['APP_RIF'] ?? 'J502098500',
                $_ENV['APP_TELEFONO'] ?? '04267536320',
                $_ENV['APP_NAME'] ?? 'UbikGo'
            ),
            new \App\Application\Contracts\UseCases\ListSellerContracts(
                $getContractRepo(),
                $getPlanRepo(),
                $getUserRepo(),
                $getVehicleRepo(),
                $getOrderRepo()
            ),
            new \App\Application\Contracts\UseCases\RenewServiceContract(
                $getContractRepo(),
                $getPlanRepo(),
                $getVehicleRepo(),
                $getOrderRepo(),
                $getExchangeService(),
                $_ENV['APP_RIF'] ?? 'J502098500',
                $_ENV['APP_TELEFONO'] ?? '04267536320',
                $_ENV['APP_NAME'] ?? 'UbikGo'
            ),
            $getUserRepo()
        ),
        DriverController::class => fn() => new DriverController(
            new RegisterDriverCrane(
                $getVehicleRepo(),
                $getUserRepo(),
                $getDocumentRepo(),
                $documentExtractor,
                $getCatalogRepo(),
                $getEnqueueS3()
            ),
            new UploadDriverLicense(
                $getDocumentRepo(),
                $getUserRepo(),
                $getEnqueueS3()
            ),
            new GetDriverFleetStatus(
                $getUserRepo(),
                $getDocumentRepo(),
                $getVehicleRepo()
            ),
            $getVehicleRepo()
        ),
    ],
    'services' => [
        QueueServiceInterface::class      => $getQueueService,
        StorageServiceInterface::class    => $getStorageService,
        EnqueueDocumentS3Upload::class    => $getEnqueueS3,
        ProcessDocumentS3UploadJob::class => $getProcessS3,
    ],
    'route_middlewares' => [
        // Lazy: si la ruta es pública, no instanciamos AuthMiddleware ni tocamos la BD
        'auth'       => fn() => new AuthMiddleware($jwtService, $getUserRepo()),
        'kyc'        => fn(?string $mode = null) => new EnsureKycVerifiedMiddleware($getDocumentRepo(), $mode ?? 'full'),
        'role'       => fn(string $role) => AuthorizeMiddleware::role($role),
        'permission' => fn(string $perm) => AuthorizeMiddleware::permission($perm),
        'can'        => fn(string $perm) => AuthorizeMiddleware::permission($perm),
    ],
    'global_middlewares' => [
        new CorsMiddleware(),
        new EnforceJsonResponseMiddleware(),
        new ThrottleMiddleware($getRedis()),
    ]
];