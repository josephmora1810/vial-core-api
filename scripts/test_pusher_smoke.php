<?php

/**
 * Script de Verificación y Smoke Test de WebSockets (Pusher Channels)
 * 
 * Uso:
 *   php scripts/test_pusher_smoke.php
 *   php scripts/test_pusher_smoke.php mi-canal-personal mi-evento
 */

use App\Core\DotEnv;
use App\Infrastructure\Broadcasting\PusherBroadcaster;
use App\Infrastructure\Http\CurlHttpClient;
use App\Infrastructure\Logging\IntegrationLogger;

$rootPath = dirname(__DIR__);
require_once "{$rootPath}/autoload.php";

$envFile = "{$rootPath}/.env";
if (file_exists($envFile)) {
    (new DotEnv($envFile))->load();
}

$appId     = $_ENV['PUSHER_APP_ID'] ?? '';
$appKey    = $_ENV['PUSHER_APP_KEY'] ?? '';
$appSecret = $_ENV['PUSHER_APP_SECRET'] ?? '';
$cluster   = $_ENV['PUSHER_APP_CLUSTER'] ?? 'sa1';

echo "========================================================\n";
echo "   UBIKGO CORE API - PUSHER WEBSOCKETS SMOKE TEST       \n";
echo "========================================================\n\n";

echo "Configuración detectada en .env:\n";
echo "  - App ID:  {$appId}\n";
echo "  - App Key: {$appKey}\n";
echo "  - Cluster: {$cluster}\n";
echo "  - Secret:  " . (empty($appSecret) ? "NO CONFIGURADO" : substr($appSecret, 0, 4) . "****************") . "\n\n";

if (empty($appId) || empty($appKey) || empty($appSecret)) {
    echo "ERROR: Las credenciales de Pusher no están completas en el archivo .env.\n";
    exit(1);
}

$channel = $argv[1] ?? 'cranes-radar';
$event   = $argv[2] ?? 'test-broadcast-smoke';

echo "Transmitiendo evento en tiempo real...\n";
echo "  - Canal objetivo: '{$channel}'\n";
echo "  - Nombre evento:  '{$event}'\n\n";

$httpClient = new CurlHttpClient(timeout: 10, connectTimeout: 5);
$logger = new IntegrationLogger();

$broadcaster = new PusherBroadcaster(
    httpClient: $httpClient,
    appId: $appId,
    appKey: $appKey,
    appSecret: $appSecret,
    cluster: $cluster,
    port: 443,
    scheme: 'https',
    logger: $logger
);

$testPayload = [
    'title'       => 'Smoke Test UbikGo Telemetría',
    'message'     => 'Emisión en vivo exitosa desde el Core API de UbikGo hacia Pusher WebSockets.',
    'channel'     => $channel,
    'server_time' => date('Y-m-d H:i:s'),
    'test_id'     => bin2hex(random_bytes(6)),
];

$startTime = microtime(true);
$success = $broadcaster->broadcast($channel, $event, $testPayload);
$durationMs = round((microtime(true) - $startTime) * 1000, 2);

echo "========================================================\n";
if ($success) {
    echo "  [RESULTADO: PASS] Evento transmitido exitosamente ({$durationMs} ms)\n";
    echo "========================================================\n\n";
    echo "Consejo: Puedes verificar la llegada inmediata del paquete abriendo:\n";
    echo "  -> Pusher Dashboard -> Tu App ({$appId}) -> Debug Console\n";
    echo "  -> Verás el canal '{$channel}' con el evento '{$event}'.\n\n";
    exit(0);
} else {
    echo "  [RESULTADO: FAIL] No se pudo transmitir el evento a Pusher.\n";
    echo "========================================================\n\n";
    echo "Revisa las credenciales de Pusher en el archivo .env o la conexión a internet.\n\n";
    exit(1);
}
