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


$getUserRepo = fn() => new MariaDbUserRepository($getDb());
$getTokenRepo = fn() => new MariaDbPersonalAccessTokenRepository($getDb());
$getRateLimiter = fn() => new RedisAccountRateLimiter($getRedis());


return [
    'controllers' => [
        AuthController::class => fn() => new AuthController(
            new LoginUser($getUserRepo(), $getTokenRepo(), $jwtService, $getRateLimiter()),
            new RegisterUser($getUserRepo(), $documentExtractor, $jwtService, $getTokenRepo()),
            new RefreshToken($getTokenRepo(), $getUserRepo(), $jwtService),
            new LogoutUser($getTokenRepo()),
            new SendPasswordResetCode($getUserRepo(), $mailer, $getRedis()),
            new ResetPassword($getUserRepo(), $getTokenRepo(), $getRedis())
        ),
        HomeController::class => fn() => new HomeController(),
    ],
    'route_middlewares' => [
        // Lazy: si la ruta es pública, no instanciamos AuthMiddleware ni tocamos la BD
        'auth' => fn() => new AuthMiddleware($jwtService, $getUserRepo()),
    ],
    'global_middlewares' => [
        new CorsMiddleware(),
        new ThrottleMiddleware($getRedis()),
    ]
];