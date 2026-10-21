<?php

/**
 * Cronjob: Sincronización de Tasas Oficiales BCV (USD y EUR) con Bancaribe
 *
 * Consulta las tasas del día en el microservicio BCV de Bancaribe, las persiste
 * en MariaDB (`exchange_rates`) y actualiza la caché en Redis (`exchange_rate:*`),
 * invalidando además la caché de precios en VES del catálogo de planes.
 *
 * Ejemplo Crontab (cada hora):
 *   0 * * * * php /ruta/al/proyecto/scripts/sync_exchange_rates.php >> /var/log/ubikgo_cron.log 2>&1
 */

require_once __DIR__ . '/../autoload.php';

use App\Core\Env;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Redis;
use App\Infrastructure\Database\Repositories\Contracts\CachedPlanRepository;
use App\Infrastructure\Database\Repositories\Contracts\MariaDbPlanRepository;
use App\Infrastructure\Database\Repositories\Payments\MariaDbBankTokenRepository;
use App\Infrastructure\Database\Repositories\Payments\MariaDbExchangeRateRepository;
use App\Infrastructure\Http\CurlHttpClient;
use App\Infrastructure\Services\Bancaribe\BancaribeAuthService;
use App\Infrastructure\Services\Bancaribe\BancaribeExchangeService;

$rootPath = dirname(__DIR__);
require_once "{$rootPath}/autoload.php";

try {
    $pdo = (new Database())->getConnection();
    $redis = new Redis();
    $httpClient = new CurlHttpClient(timeout: 30, connectTimeout: 5);
    $isSandbox = filter_var($_ENV['BANCARIBE_SANDBOX'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $isLocalEnv = ($_ENV['APP_ENV'] ?? 'local') === 'local';

    $authService = new BancaribeAuthService(
        httpClient: $httpClient,
        tokenRepository: new MariaDbBankTokenRepository($pdo),
        redis: $redis,
        baseAuthUrl: $_ENV['BANCARIBE_BASE_AUTH_URL'] ?? '',
        oauth2Endpoint: $_ENV['BANCARIBE_OAUTH2_ENDPOINT'] ?? 'oauth2/token',
        customerKey: $_ENV['BANCARIBE_COSTUMER_KEY'] ?? '',
        customerSecret: $_ENV['BANCARIBE_COSTUMER_SECRET'] ?? '',
        sandboxMode: $isSandbox
    );

    $exchangeService = new BancaribeExchangeService(
        authService: $authService,
        httpClient: $httpClient,
        rateRepository: new MariaDbExchangeRateRepository($pdo),
        redis: $redis,
        baseUrl: $_ENV['BANCARIBE_BASE_URL'] ?? '',
        exchangeEndpoint: $_ENV['BANCARIBE_EXCHANGE_ENDPOINT'] ?? 'BCVMicro/1.0.0/consultaTasa/tasa',
        exchangeHash: $_ENV['BANCARIBE_EXCHANGE_HASH'] ?? '',
        rif: $_ENV['APP_RIF'] ?? 'J502098500',
        isLocalEnv: $isLocalEnv,
        fallbackRate: (float)($_ENV['BANCARIBE_FALLBACK_RATE'] ?? 40.00)
    );

    $synced = $exchangeService->fetchAndSaveTodayRates();

    // Invalidar caché de planes para que la landing page refleje de inmediato la nueva tasa BCV
    $planRepo = new CachedPlanRepository(new MariaDbPlanRepository($pdo), $redis);
    $planRepo->invalidatePlansCache();

    echo sprintf(
        "[%s] [CRON:EXCHANGE_RATES] Tasas BCV sincronizadas exitosamente (%d registros).\n",
        date('Y-m-d H:i:s'),
        count($synced)
    );
    exit(0);
} catch (Throwable $e) {
    fwrite(
        STDERR,
        sprintf("[%s] [CRON:EXCHANGE_RATES] ERROR: %s\n", date('Y-m-d H:i:s'), $e->getMessage())
    );
    exit(1);
}
