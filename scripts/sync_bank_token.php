<?php

/**
 * Cronjob: Renovación Proactiva del Token OAuth2 de Bancaribe
 *
 * Solicita o refresca el token OAuth2 ante el servidor de autenticación de Bancaribe
 * y lo almacena en MariaDB (`bank_tokens`) y en Redis (`bancaribe:access_token`),
 * garantizando que las peticiones de cobro no sufran latencia de autenticación.
 *
 * Ejemplo Crontab (cada 25 minutos):
 *   * //25 * * * * php /ruta/al/proyecto/scripts/sync_bank_token.php >> /var/log/ubikgo_cron.log 2>&1 
 */

require_once __DIR__ . '/../autoload.php';

use App\Core\DotEnv;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Redis;
use App\Infrastructure\Database\Repositories\Payments\MariaDbBankTokenRepository;
use App\Infrastructure\Http\CurlHttpClient;
use App\Infrastructure\Services\Bancaribe\BancaribeAuthService;

$rootPath = dirname(__DIR__);
require_once "{$rootPath}/autoload.php";

$envFile = $rootPath . "/.env";
$dotEnv = new DotEnv($envFile);
$dotEnv->load();

try {
    $pdo = (new Database())->getConnection();
    $redis = new Redis();
    $httpClient = new CurlHttpClient(timeout: 30, connectTimeout: 5);
    $tokenRepo = new MariaDbBankTokenRepository($pdo);
    $isSandbox = filter_var($_ENV['BANCARIBE_SANDBOX'] ?? false, FILTER_VALIDATE_BOOLEAN);

    $authService = new BancaribeAuthService(
        httpClient: $httpClient,
        tokenRepository: $tokenRepo,
        redis: $redis,
        baseAuthUrl: $_ENV['BANCARIBE_BASE_AUTH_URL'] ?? '',
        oauth2Endpoint: $_ENV['BANCARIBE_OAUTH2_ENDPOINT'] ?? 'oauth2/token',
        customerKey: $_ENV['BANCARIBE_COSTUMER_KEY'] ?? '',
        customerSecret: $_ENV['BANCARIBE_COSTUMER_SECRET'] ?? '',
        sandboxMode: $isSandbox
    );

    $token = $authService->getAccessToken();
    $preview = substr($token, 0, 12) . '...';

    echo sprintf(
        "[%s] [CRON:BANK_TOKEN] Token OAuth2 de Bancaribe sincronizado exitosamente (%s).\n",
        date('Y-m-d H:i:s'),
        $preview
    );
    exit(0);
} catch (Throwable $e) {
    fwrite(
        STDERR,
        sprintf("[%s] [CRON:BANK_TOKEN] ERROR: %s\n", date('Y-m-d H:i:s'), $e->getMessage())
    );
    exit(1);
}
