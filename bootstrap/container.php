<?php
// bootstrap/container.php

use App\Application\UseCases\LoginUser;
use App\Application\UseCases\LogoutUser;
use App\Application\UseCases\RefreshToken;
use App\Application\UseCases\RegisterUser;
use App\Application\UseCases\ResetPassword;
use App\Application\UseCases\SendPasswordResetCode;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Core\JwtService;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Redis;
use App\Infrastructure\Database\Repositories\MariaDbPersonalAccessTokenRepository;
use App\Infrastructure\Database\Repositories\MariaDbUserRepository;
use App\Infrastructure\Http\CurlHttpClient;
use App\Infrastructure\Mail\SmtpMailer;
use App\Infrastructure\Services\LaVenezolanaDocumentExtractor;
use App\Infrastructure\Services\RedisAccountRateLimiter;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CorsMiddleware;
use App\Middlewares\ThrottleMiddleware;
use App\Application\UseCases\GetKycStatus;
use App\Application\UseCases\UploadKycDocument;
use App\Controllers\DocumentController;
use App\Infrastructure\Database\Repositories\MariaDbDocumentRepository;
use App\Middlewares\AuthorizeMiddleware;
use App\Middlewares\EnsureKycVerifiedMiddleware;
use App\Middlewares\EnforceJsonResponseMiddleware;

// Conexiones con MariaDB y Redis
// instanciadas de manera lazy para no abrir conexiones innecesarias en rutas públicas
$getDb = function () {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new Database()->getConnection();
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

//Servicio Mailer
$mailer = new SmtpMailer(
    host: $_ENV['SMTP_HOST'] ?? 'localhost',
    port: (int)($_ENV['SMTP_PORT'] ?? 465),
    username: $_ENV['SMTP_USERNAME'] ?? '',
    password: $_ENV['SMTP_PASSWORD'] ?? '',
    fromAddress: $_ENV['MAIL_FROM_ADDRESS'] ?? 'soporte@ubikgo.com',
    fromName: $_ENV['MAIL_FROM_NAME'] ?? 'UbikGo',
    encryption: $_ENV['SMTP_ENCRYPTION'] ?? 'ssl'
);


use App\Application\UseCases\CreateSavedAddress;
use App\Application\UseCases\DeleteSavedAddress;
use App\Application\UseCases\DeleteVehicle;
use App\Application\UseCases\ListSavedAddresses;
use App\Application\UseCases\ListUserVehicles;
use App\Application\UseCases\RegisterVehicle;
use App\Application\UseCases\ScanVehicleRegistration;
use App\Controllers\SavedAddressController;
use App\Controllers\VehicleController;
use App\Domain\Services\VehicleMatcherService;
use App\Infrastructure\Database\Repositories\CachedVehicleCatalogRepository;
use App\Infrastructure\Database\Repositories\MariaDbSavedAddressRepository;
use App\Infrastructure\Database\Repositories\MariaDbVehicleCatalogRepository;
use App\Infrastructure\Database\Repositories\MariaDbVehicleRepository;

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

return [
    'controllers' => [
        AuthController::class => fn() => new AuthController(
            new LoginUser($getUserRepo(), $getTokenRepo(), $jwtService, $getRateLimiter()),
            new RegisterUser($getUserRepo(), $documentExtractor, $jwtService, $getTokenRepo(), $getDocumentRepo()),
            new RefreshToken($getTokenRepo(), $getUserRepo(), $jwtService),
            new LogoutUser($getTokenRepo()),
            new SendPasswordResetCode($getUserRepo(), $mailer, $getRedis()),
            new ResetPassword($getUserRepo(), $getTokenRepo(), $getRedis()),
            new \App\Application\UseCases\ScanIdentityCard($documentExtractor)
        ),
        HomeController::class => fn() => new HomeController(),
        DocumentController::class => fn() => new DocumentController(
            new UploadKycDocument($getDocumentRepo(), $getUserRepo(), $documentExtractor),
            new GetKycStatus($getDocumentRepo(), $getUserRepo())
        ),
        SavedAddressController::class => fn() => new SavedAddressController(
            new CreateSavedAddress($getSavedAddressRepo(), $getUserRepo()),
            new ListSavedAddresses($getSavedAddressRepo(), $getUserRepo()),
            new DeleteSavedAddress($getSavedAddressRepo())
        ),
        VehicleController::class => fn() => new VehicleController(
            new RegisterVehicle($getVehicleRepo(), $getUserRepo(), $getDocumentRepo(), $getCatalogRepo()),
            new ListUserVehicles($getVehicleRepo(), $getUserRepo()),
            new DeleteVehicle($getVehicleRepo()),
            new ScanVehicleRegistration($documentExtractor, new VehicleMatcherService($getCatalogRepo())),
            $getCatalogRepo()
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