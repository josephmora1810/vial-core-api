<?php

/**
 * Cronjob CLI: Cancelación por Timeout de Solicitudes SOS no atendidas (FR-24-C)
 * Uso en consola / Crontab / Programador de Tareas de Windows:
 *   php scripts/expire_sos_timeouts.php [minutos_tolerancia]
 *
 * Busca todas las solicitudes de asistencia vial (tow_services) en estado PENDING
 * que hayan superado el tiempo máximo sin que ningún conductor las acepte (default: 10 minutos).
 * Pasa su estado a CANCELLED_BY_TIMEOUT, reembolsa el pago al wallet_balance del cliente
 * (si pagó express o km extra) y difunde la cancelación vía Pusher Channels.
 */

require_once __DIR__ . '/../autoload.php';

use App\Application\Towing\UseCases\ExpireOverdueSosServices;
use App\Core\DotEnv;
use App\Infrastructure\Broadcasting\PusherBroadcaster;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Repositories\Contracts\MariaDbServiceContractRepository;
use App\Infrastructure\Database\Repositories\Payments\MariaDbOrderRepository;
use App\Infrastructure\Database\Repositories\Towing\MariaDbTowServiceRepository;
use App\Infrastructure\Database\Repositories\Users\MariaDbUserRepository;
use App\Infrastructure\Http\CurlHttpClient;

(new DotEnv(__DIR__ . '/../.env'))->load();

$timeoutMinutes = isset($argv[1]) && is_numeric($argv[1]) ? (int)$argv[1] : 10;

try {
    $pdo = (new Database())->getConnection();

    $towServiceRepo = new MariaDbTowServiceRepository($pdo);
    $orderRepo = new MariaDbOrderRepository($pdo);
    $userRepo = new MariaDbUserRepository($pdo);
    $contractRepo = new MariaDbServiceContractRepository($pdo);

    $pusherAppId = getenv('PUSHER_APP_ID') ?: null;
    $pusherKey = getenv('PUSHER_APP_KEY') ?: null;
    $pusherSecret = getenv('PUSHER_APP_SECRET') ?: null;
    $pusherCluster = getenv('PUSHER_APP_CLUSTER') ?: 'sa1';

    $broadcaster = null;
    if ($pusherAppId && $pusherKey && $pusherSecret) {
        $broadcaster = new PusherBroadcaster(
            httpClient: new CurlHttpClient(),
            appId: $pusherAppId,
            appKey: $pusherKey,
            appSecret: $pusherSecret,
            cluster: $pusherCluster
        );
    }

    $useCase = new ExpireOverdueSosServices(
        towServiceRepository: $towServiceRepo,
        orderRepository: $orderRepo,
        userRepository: $userRepo,
        contractRepository: $contractRepo,
        broadcaster: $broadcaster
    );

    $result = $useCase->execute($timeoutMinutes);

    $timestamp = (new DateTimeImmutable())->format('Y-m-d H:i:s');
    echo sprintf(
        "[%s] Cronjob SOS Timeouts finalizado. Expirados: %d | Reembolsados a wallet: %d (Tolerancia: %d min)\n",
        $timestamp,
        $result['expired_count'],
        $result['refunded_count'],
        $timeoutMinutes
    );
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, sprintf("[ERROR CRON SOS TIMEOUTS] %s\n", $e->getMessage()));
    exit(1);
}
