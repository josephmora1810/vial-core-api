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
use App\Application\Landing\UseCases\GetActiveHomeGallery;
use App\Application\Landing\UseCases\ManageHomeGallery;
use App\Application\Users\UseCases\ActivateUser;
use App\Application\Users\UseCases\SuspendUser;
use App\Application\Users\UseCases\UploadUserAvatar;
use App\Controllers\AdminCommissionController;
use App\Controllers\AuthController;
use App\Controllers\ContractController;
use App\Controllers\DocumentController;
use App\Controllers\DriverController;
use App\Controllers\HomeController;
use App\Controllers\LandingController;
use App\Controllers\PaymentController;
use App\Controllers\PlanController;
use App\Controllers\SavedAddressController;
use App\Controllers\TowingController;
use App\Controllers\UserModerationController;
use App\Controllers\VehicleController;
use App\Application\Towing\UseCases\AcceptSosService;
use App\Application\Towing\UseCases\ArriveTowService;
use App\Application\Towing\UseCases\CancelTowService;
use App\Application\Towing\UseCases\CompleteTowService;
use App\Application\Towing\UseCases\ExpireOverdueSosServices;
use App\Application\Towing\UseCases\FindNearbyPendingServices;
use App\Application\Towing\UseCases\GetTowService;
use App\Application\Towing\UseCases\RateTowService;
use App\Application\Towing\UseCases\RequestSosAssistance;
use App\Application\Towing\UseCases\UpdateCraneTelemetry;
use App\Infrastructure\Database\Repositories\Towing\MariaDbRatingRepository;
use App\Infrastructure\Database\Repositories\Towing\MariaDbTowServiceLogRepository;
use App\Infrastructure\Database\Repositories\Towing\MariaDbTowServiceRepository;
use App\Core\JwtService;
use App\Domain\Shared\Services\BroadcasterInterface;
use App\Domain\Shared\Services\QueueServiceInterface;
use App\Domain\Shared\Services\StorageServiceInterface;
use App\Infrastructure\Broadcasting\PusherBroadcaster;
use App\Domain\Vehicles\Services\VehicleMatcherService;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Redis;
use App\Infrastructure\Logging\IntegrationLogger;
use App\Infrastructure\Queue\RedisQueueService;
use App\Infrastructure\Services\Towing\InMemoryCoverageZoneRepository;
use App\Infrastructure\Storage\AwsS3StorageService;
use App\Infrastructure\Database\Repositories\Commissions\MariaDbCommissionRepository;
use App\Infrastructure\Database\Repositories\Commissions\MariaDbSellerProfileRepository;
use App\Infrastructure\Database\Repositories\Contracts\CachedPlanRepository;
use App\Infrastructure\Database\Repositories\Vehicles\CachedVehicleCatalogRepository;
use App\Infrastructure\Database\Repositories\Payments\MariaDbBankTokenRepository;
use App\Infrastructure\Database\Repositories\Auth\MariaDbDocumentRepository;
use App\Infrastructure\Database\Repositories\Payments\MariaDbExchangeRateRepository;
use App\Infrastructure\Database\Repositories\Payments\MariaDbOrderRepository;
use App\Infrastructure\Database\Repositories\Payments\MariaDbPaymentRepository;
use App\Infrastructure\Database\Repositories\Auth\MariaDbPersonalAccessTokenRepository;
use App\Infrastructure\Database\Repositories\Contracts\MariaDbPlanRepository;
use App\Infrastructure\Database\Repositories\Landing\MariaDbHomeGalleryRepository;
use App\Infrastructure\Database\Repositories\Shared\CachedImageRepository;
use App\Infrastructure\Database\Repositories\Shared\MariaDbImageRepository;
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
use App\Infrastructure\Services\Towing\OsrmRoutingService;

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

// Registrar patrones de servicios externos en IntegrationLogger (Cumple OCP)
IntegrationLogger::registerServicePattern('osrm', ['osrm', 'project-osrm.org', ':5000']);
IntegrationLogger::registerServicePattern('pusher', ['pusher.com', 'pusherapp.com']);

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

$getCoverageZones = fn() => new InMemoryCoverageZoneRepository();
$getRoutingService = fn() => new OsrmRoutingService(
    httpClient: $httpClient,
    baseUrl: $_ENV['OSRM_BASE_URL'] ?? null
);
$getTowServiceRepo = fn() => new MariaDbTowServiceRepository($getDb());
$getTowServiceLogRepo = fn() => new MariaDbTowServiceLogRepository($getDb());
$getRatingRepo = fn() => new MariaDbRatingRepository($getDb());

// Servicio de WebSockets / Broadcasting (Pusher Channels)
$getBroadcaster = fn() => new PusherBroadcaster(
    httpClient: $httpClient,
    appId: $_ENV['PUSHER_APP_ID'] ?? '',
    appKey: $_ENV['PUSHER_APP_KEY'] ?? '',
    appSecret: $_ENV['PUSHER_APP_SECRET'] ?? '',
    cluster: $_ENV['PUSHER_APP_CLUSTER'] ?? 'sa1',
    port: (int)($_ENV['PUSHER_PORT'] ?? 443),
    scheme: $_ENV['PUSHER_SCHEME'] ?? 'https',
    logger: $integrationLogger
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
$getImageRepo = fn() => new CachedImageRepository(
    new MariaDbImageRepository($getDb()),
    $getRedis()
);
$getHomeGalleryRepo = fn() => new MariaDbHomeGalleryRepository($getDb(), $getImageRepo());

// Repositorios y Servicios de Comisiones y Billeteras B2B (FR-14, FR-15, FR-34)
$getSellerProfileRepo = fn() => new MariaDbSellerProfileRepository($getDb());
$getCommissionRepo = fn() => new MariaDbCommissionRepository($getDb());
$getGenerateOrderCommission = fn() => new \App\Application\Commissions\UseCases\GenerateOrderCommission(
    $getCommissionRepo(),
    $getSellerProfileRepo()
);

$isSandbox = filter_var($_ENV['BANCARIBE_SANDBOX'] ?? false, FILTER_VALIDATE_BOOLEAN);
$isPaymentSandbox = filter_var($_ENV['BANCARIBE_PAYMENT_SANDBOX'] ?? true, FILTER_VALIDATE_BOOLEAN);
$appEnv = $_ENV['APP_ENV'] ?? 'local';
$isLocalEnv = ($appEnv === 'local');

if (!$isLocalEnv && ($isSandbox || $isPaymentSandbox)) {
    throw new \LogicException(
        "Configuración inválida de seguridad: BANCARIBE_SANDBOX y BANCARIBE_PAYMENT_SANDBOX no pueden estar habilitados cuando APP_ENV no es 'local' (entorno actual: '{$appEnv}')."
    );
}

$getBancaribeAuth = fn() => new BancaribeAuthService(
    httpClient: $httpClient,
    tokenRepository: $getBankTokenRepo(),
    redis: $getRedis(),
    baseAuthUrl: $_ENV['BANCARIBE_BASE_AUTH_URL'] ?? '',
    oauth2Endpoint: $_ENV['BANCARIBE_OAUTH2_ENDPOINT'] ?? 'oauth2/token',
    customerKey: $_ENV['BANCARIBE_COSTUMER_KEY'] ?? '',
    customerSecret: $_ENV['BANCARIBE_COSTUMER_SECRET'] ?? '',
    sandboxMode: $isSandbox,
    isLocalEnv: $isLocalEnv
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
    sandboxMode: $isPaymentSandbox,
    isLocalEnv: $isLocalEnv
);

$getBalanceGateway = fn() => new BancaribeBalanceService(
    authService: $getBancaribeAuth(),
    httpClient: $httpClient,
    redis: $getRedis(),
    baseUrl: $_ENV['BANCARIBE_BASE_URL'] ?? '',
    checkBalanceEndpoint: $_ENV['BANCARIBE_CHECK_BALANCE_ENDPOINT'] ?? 'CtasSaldos/1.0.0/ctasConsultaSaldos',
    checkBalanceHash: $_ENV['BANCARIBE_CHECK_BALANCE_HASH'] ?? '',
    rif: $_ENV['APP_RIF'] ?? 'J502098500',
    sandboxMode: $isSandbox,
    isLocalEnv: $isLocalEnv
);

// Servicios de Almacenamiento S3 y Colas en Redis (NFR-20-B)
$getQueueService = fn() => new RedisQueueService($getRedis());
$getStorageService = fn() => new AwsS3StorageService(logger: $integrationLogger);
$getEnqueueS3 = fn() => new EnqueueDocumentS3Upload($getQueueService());
$getProcessS3 = fn() => new ProcessDocumentS3UploadJob(
    queueService: $getQueueService(),
    storageService: $getStorageService(),
    documentRepository: $getDocumentRepo(),
    imageRepository: $getImageRepo(),
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
            new ScanIdentityCard($documentExtractor),
            $getImageRepo()
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
        ContractController::class => fn() => new ContractController(
            checkoutServiceContract: new CheckoutServiceContract(
                $getPlanRepo(),
                $getVehicleRepo(),
                $getContractRepo(),
                $getOrderRepo(),
                $getExchangeService(),
                $_ENV['APP_RIF'] ?? 'J502098500',
                $_ENV['APP_TELEFONO'] ?? '04267536320',
                $_ENV['APP_NAME'] ?? 'UbikGo'
            ),
            listContractsAndOrders: new ListUserContractsAndOrders(
                $getContractRepo(),
                $getOrderRepo(),
                $getPlanRepo(),
                $getVehicleRepo(),
                $getExchangeService()
            ),
            generateContractPdf: new GenerateServiceContractPdf(
                $getContractRepo(),
                $getPlanRepo(),
                $getUserRepo(),
                $getVehicleRepo(),
                $getSavedAddressRepo(),
                $getCatalogRepo(),
                new FpdfContractPdfGenerator()
            ),
            cancelContractUseCase: new CancelServiceContract(
                $getContractRepo(),
                $getPlanRepo(),
                $getVehicleRepo()
            ),
            expireOverdueContractsUseCase: new \App\Application\Contracts\UseCases\ExpireOverdueContracts(
                $getContractRepo()
            ),
            renewContractUseCase: new \App\Application\Contracts\UseCases\RenewServiceContract(
                $getContractRepo(),
                $getPlanRepo(),
                $getVehicleRepo(),
                $getOrderRepo(),
                $getExchangeService(),
                $_ENV['APP_RIF'] ?? 'J502098500',
                $_ENV['APP_TELEFONO'] ?? '04267536320',
                $_ENV['APP_NAME'] ?? 'UbikGo'
            ),
            redeemContractUseCase: new \App\Application\Contracts\UseCases\RedeemServiceContract(
                $getContractRepo(),
                $getPlanRepo(),
                $getVehicleRepo()
            ),
            verifyContractUseCase: new \App\Application\Contracts\UseCases\VerifyServiceContract(
                $getContractRepo(),
                $getPlanRepo(),
                $getUserRepo(),
                $getVehicleRepo(),
                $getRedis()
            )
        ),
        PaymentController::class => fn() => new PaymentController(
            processPayment: new ProcessPayment(
                $getOrderRepo(),
                $getPaymentRepo(),
                $getContractRepo(),
                $getPlanRepo(),
                $getPaymentGateway(),
                $getExchangeService(),
                $getGenerateOrderCommission()
            ),
            handleWebhook: new HandleBancaribeWebhook(
                $getOrderRepo(),
                $getPaymentRepo(),
                $getContractRepo(),
                $getPlanRepo(),
                !empty($_ENV['BANCARIBE_WEBHOOK_SECRET']) ? $_ENV['BANCARIBE_WEBHOOK_SECRET'] : null,
                $getGenerateOrderCommission()
            ),
            listContractsAndOrders: new ListUserContractsAndOrders(
                $getContractRepo(),
                $getOrderRepo(),
                $getPlanRepo(),
                $getVehicleRepo(),
                $getExchangeService()
            ),
            exchangeRateService: $getExchangeService(),
            balanceGateway: $getBalanceGateway()
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
            $getUserRepo(),
            new \App\Application\Commissions\UseCases\ManageSellerBankAccount($getSellerProfileRepo(), $getPlanRepo()),
            new \App\Application\Commissions\UseCases\ListSellerCommissions($getCommissionRepo(), $getSellerProfileRepo(), $getOrderRepo(), $getPlanRepo()),
            new \App\Application\Commissions\UseCases\RequestCommissionWithdrawal($getCommissionRepo(), $getSellerProfileRepo()),
            $getSellerProfileRepo(),
            $getPlanRepo()
        ),
        AdminCommissionController::class => fn() => new AdminCommissionController(
            new \App\Application\Commissions\UseCases\ListAdminCommissions(
                $getCommissionRepo(),
                $getSellerProfileRepo(),
                $getUserRepo(),
                $getOrderRepo()
            ),
            new \App\Application\Commissions\UseCases\PayCommissionByAdmin(
                $getCommissionRepo(),
                $getSellerProfileRepo()
            ),
            new \App\Application\Commissions\UseCases\UpdateSellerCommissionRate(
                $getSellerProfileRepo(),
                $getUserRepo()
            )
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
        LandingController::class => fn() => new LandingController(
            new GetActiveHomeGallery($getHomeGalleryRepo()),
            new ManageHomeGallery($getHomeGalleryRepo(), $getStorageService())
        ),
        UserModerationController::class => fn() => new UserModerationController(
            new SuspendUser($getUserRepo(), $getTokenRepo(), $integrationLogger),
            new ActivateUser($getUserRepo(), $integrationLogger),
            new UploadUserAvatar($getUserRepo(), $getStorageService(), $getImageRepo())
        ),
        TowingController::class => fn() => new TowingController(
            new RequestSosAssistance(
                $getCoverageZones(),
                $getVehicleRepo(),
                $getRoutingService(),
                $getContractRepo(),
                $getTowServiceRepo(),
                $getOrderRepo(),
                $getExchangeService(),
                $getSavedAddressRepo(),
                $getBroadcaster()
            ),
            new AcceptSosService($getTowServiceRepo(), $getBroadcaster()),
            new UpdateCraneTelemetry($getTowServiceRepo(), $getTowServiceLogRepo(), $getBroadcaster()),
            new ArriveTowService($getTowServiceRepo()),
            new CompleteTowService($getTowServiceRepo(), $getContractRepo()),
            new CancelTowService($getTowServiceRepo(), $getBroadcaster()),
            new RateTowService($getTowServiceRepo(), $getRatingRepo(), $getRedis()),
            new GetTowService($getTowServiceRepo(), $getVehicleRepo()),
            new FindNearbyPendingServices($getTowServiceRepo(), $getVehicleRepo())
        ),
        \App\Controllers\PusherWebhookController::class => fn() => new \App\Controllers\PusherWebhookController(
            appKey: $_ENV['PUSHER_APP_KEY'] ?? '',
            appSecret: $_ENV['PUSHER_APP_SECRET'] ?? '',
            logger: $integrationLogger
        ),
    ],
    'services' => [
        BroadcasterInterface::class       => $getBroadcaster,
        QueueServiceInterface::class      => $getQueueService,
        StorageServiceInterface::class    => $getStorageService,
        EnqueueDocumentS3Upload::class    => $getEnqueueS3,
        ProcessDocumentS3UploadJob::class => $getProcessS3,
        ExpireOverdueSosServices::class   => fn() => new ExpireOverdueSosServices(
            $getTowServiceRepo(),
            $getOrderRepo(),
            $getUserRepo(),
            $getContractRepo(),
            $getBroadcaster()
        ),
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