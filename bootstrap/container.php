<?php
// bootstrap/container.php

use App\Application\Auth\UseCases\{
    GetKycStatus,
    LoginUser,
    LogoutUser,
    RefreshToken,
    RegisterUser,
    ResetPassword,
    ReviewKycDocument,
    ScanIdentityCard,
    SendPasswordResetCode,
    UploadKycDocument
};
use App\Application\Commissions\UseCases\{
    GenerateOrderCommission,
    ListAdminCommissions,
    ListSellerCommissions,
    ManageSellerBankAccount,
    PayCommissionByAdmin,
    RequestCommissionWithdrawal,
    UpdateSellerCommissionRate
};
use App\Application\Contracts\UseCases\{
    CancelServiceContract,
    CheckoutServiceContract,
    ExpireOverdueContracts,
    GenerateServiceContractPdf,
    ListPlans,
    ListSellerContracts,
    ListUserContractsAndOrders,
    ManagePlan,
    RedeemServiceContract,
    RenewServiceContract,
    VerifyServiceContract
};
use App\Application\Documents\UseCases\{
    EnqueueDocumentS3Upload,
    ProcessDocumentS3UploadJob
};
use App\Application\Driver\UseCases\{
    GetDriverFleetStatus,
    RegisterDriverCrane,
    UploadDriverLicense
};
use App\Application\Landing\UseCases\{
    GetActiveHomeGallery,
    ManageHomeGallery
};
use App\Application\Payments\UseCases\{
    HandleBancaribeWebhook,
    ProcessPayment
};
use App\Application\Towing\UseCases\{
    AcceptSosService,
    ArriveTowService,
    CancelTowService,
    CompleteTowService,
    ExpireOverdueSosServices,
    FindNearbyPendingServices,
    GetTowService,
    RateTowService,
    RequestSosAssistance,
    UpdateCraneTelemetry
};
use App\Application\Users\UseCases\{
    ActivateUser,
    CreateSavedAddress,
    DeleteSavedAddress,
    ListSavedAddresses,
    RegisterClientBySeller,
    SearchClientByNationalId,
    SuspendUser,
    UploadUserAvatar
};
use App\Application\Vehicles\UseCases\{
    DeleteVehicle,
    ListUserVehicles,
    RegisterVehicle,
    ScanVehicleRegistration
};
use App\Controllers\{
    AdminCommissionController,
    AuthController,
    BroadcastingController,
    ContractController,
    DocumentController,
    DriverController,
    HomeController,
    LandingController,
    PaymentController,
    PlanController,
    PusherWebhookController,
    SavedAddressController,
    SellerController,
    TowingController,
    UserModerationController,
    VehicleController
};
use App\Core\JwtService;
use App\Domain\Shared\Services\{
    BroadcasterInterface,
    QueueServiceInterface,
    StorageServiceInterface
};
use App\Domain\Vehicles\Services\VehicleMatcherService;
use App\Infrastructure\Broadcasting\PusherBroadcaster;
use App\Infrastructure\Database\{
    Database,
    Redis
};
use App\Infrastructure\Database\Repositories\Auth\{
    MariaDbDocumentRepository,
    MariaDbPersonalAccessTokenRepository
};
use App\Infrastructure\Database\Repositories\Commissions\{
    MariaDbCommissionRepository,
    MariaDbSellerProfileRepository
};
use App\Infrastructure\Database\Repositories\Contracts\{
    CachedPlanRepository,
    MariaDbPlanRepository,
    MariaDbServiceContractRepository
};
use App\Infrastructure\Database\Repositories\Landing\MariaDbHomeGalleryRepository;
use App\Infrastructure\Database\Repositories\Payments\{
    MariaDbBankTokenRepository,
    MariaDbExchangeRateRepository,
    MariaDbOrderRepository,
    MariaDbPaymentRepository
};
use App\Infrastructure\Database\Repositories\Shared\{
    CachedImageRepository,
    MariaDbImageRepository
};
use App\Infrastructure\Database\Repositories\Towing\{
    MariaDbRatingRepository,
    MariaDbTowServiceLogRepository,
    MariaDbTowServiceRepository
};
use App\Infrastructure\Database\Repositories\Users\{
    MariaDbSavedAddressRepository,
    MariaDbUserRepository
};
use App\Infrastructure\Database\Repositories\Vehicles\{
    CachedVehicleCatalogRepository,
    MariaDbVehicleCatalogRepository,
    MariaDbVehicleRepository
};
use App\Infrastructure\Http\CurlHttpClient;
use App\Infrastructure\Logging\IntegrationLogger;
use App\Infrastructure\Mail\SmtpMailer;
use App\Infrastructure\Pdf\FpdfContractPdfGenerator;
use App\Infrastructure\Queue\RedisQueueService;
use App\Infrastructure\Services\Bancaribe\{
    BancaribeAuthService,
    BancaribeBalanceService,
    BancaribeExchangeService,
    BancaribePaymentService
};
use App\Infrastructure\Services\LaVenezolanaDocumentExtractor;
use App\Infrastructure\Services\RedisAccountRateLimiter;
use App\Infrastructure\Services\Towing\{
    InMemoryCoverageZoneRepository,
    OsrmRoutingService
};
use App\Infrastructure\Storage\AwsS3StorageService;
use App\Middlewares\{
    AuthMiddleware,
    AuthorizeMiddleware,
    CorsMiddleware,
    EnforceJsonResponseMiddleware,
    EnsureKycVerifiedMiddleware,
    ThrottleMiddleware
};

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
$getGenerateOrderCommission = fn() => new GenerateOrderCommission(
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
            new ReviewKycDocument($getDocumentRepo(), $getVehicleRepo())
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
            expireOverdueContractsUseCase: new ExpireOverdueContracts(
                $getContractRepo()
            ),
            renewContractUseCase: new RenewServiceContract(
                $getContractRepo(),
                $getPlanRepo(),
                $getVehicleRepo(),
                $getOrderRepo(),
                $getExchangeService(),
                $_ENV['APP_RIF'] ?? 'J502098500',
                $_ENV['APP_TELEFONO'] ?? '04267536320',
                $_ENV['APP_NAME'] ?? 'UbikGo'
            ),
            redeemContractUseCase: new RedeemServiceContract(
                $getContractRepo(),
                $getPlanRepo(),
                $getVehicleRepo()
            ),
            verifyContractUseCase: new VerifyServiceContract(
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
                $getGenerateOrderCommission(),
                $getTowServiceRepo(),
                $getBroadcaster()
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
        SellerController::class => fn() => new SellerController(
            new SearchClientByNationalId(
                $getUserRepo(),
                $getVehicleRepo(),
                $getContractRepo(),
                $getPlanRepo(),
                $getRedis()
            ),
            new RegisterClientBySeller(
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
            new ListSellerContracts(
                $getContractRepo(),
                $getPlanRepo(),
                $getUserRepo(),
                $getVehicleRepo(),
                $getOrderRepo()
            ),
            new RenewServiceContract(
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
            new ManageSellerBankAccount($getSellerProfileRepo(), $getPlanRepo()),
            new ListSellerCommissions($getCommissionRepo(), $getSellerProfileRepo(), $getOrderRepo(), $getPlanRepo()),
            new RequestCommissionWithdrawal($getCommissionRepo(), $getSellerProfileRepo()),
            $getSellerProfileRepo(),
            $getPlanRepo()
        ),
        AdminCommissionController::class => fn() => new AdminCommissionController(
            new ListAdminCommissions(
                $getCommissionRepo(),
                $getSellerProfileRepo(),
                $getUserRepo(),
                $getOrderRepo()
            ),
            new PayCommissionByAdmin(
                $getCommissionRepo(),
                $getSellerProfileRepo()
            ),
            new UpdateSellerCommissionRate(
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
            new AcceptSosService($getTowServiceRepo(), $getBroadcaster(), $getOrderRepo()),
            new UpdateCraneTelemetry($getTowServiceRepo(), $getTowServiceLogRepo(), $getBroadcaster()),
            new ArriveTowService($getTowServiceRepo()),
            new CompleteTowService($getTowServiceRepo(), $getContractRepo()),
            new CancelTowService($getTowServiceRepo(), $getBroadcaster()),
            new RateTowService($getTowServiceRepo(), $getRatingRepo(), $getRedis()),
            new GetTowService($getTowServiceRepo(), $getVehicleRepo()),
            new FindNearbyPendingServices($getTowServiceRepo(), $getVehicleRepo())
        ),
        PusherWebhookController::class => fn() => new PusherWebhookController(
            appKey: $_ENV['PUSHER_APP_KEY'] ?? '',
            appSecret: $_ENV['PUSHER_APP_SECRET'] ?? '',
            logger: $integrationLogger
        ),
        BroadcastingController::class => fn() => new BroadcastingController(
            $getBroadcaster(),
            $getTowServiceRepo()
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
