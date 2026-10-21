<?php

declare(strict_types=1);

/**
 * Worker CLI de Colas para Subida de Documentos a AWS S3
 *
 * Uso:
 *   php scripts/process_s3_queue.php                # Procesa hasta 50 trabajos en lote
 *   php scripts/process_s3_queue.php --daemon       # Ejecuta en bucle continuo de segundo plano
 *   php scripts/process_s3_queue.php --limit=100    # Procesa hasta 100 trabajos
 */

require_once dirname(__DIR__) . '/autoload.php';

use App\Application\Documents\UseCases\EnqueueDocumentS3Upload;
use App\Application\Documents\UseCases\ProcessDocumentS3UploadJob;
use App\Core\DotEnv;
use App\Domain\Shared\Services\QueueServiceInterface;

(new DotEnv(dirname(__DIR__) . '/.env'))->load();

/** @var array<string, mixed> $container */
$container = require dirname(__DIR__) . '/bootstrap/container.php';

/** @var ProcessDocumentS3UploadJob $processor */
$processor = $container['services'][ProcessDocumentS3UploadJob::class]();
/** @var QueueServiceInterface $queueService */
$queueService = $container['services'][QueueServiceInterface::class]();

$isDaemon = in_array('--daemon', $argv ?? [], true);
$limit = 50;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int)substr($arg, 8));
    }
}

$now = date('Y-m-d H:i:s');
echo "[{$now}] Iniciando worker de subida S3 (" . ($isDaemon ? 'Modo Daemon' : "Lote máx: {$limit}") . ")..." . PHP_EOL;

$processedCount = 0;
$successCount = 0;
$failCount = 0;

do {
    $pending = $queueService->size(EnqueueDocumentS3Upload::QUEUE_NAME);
    if ($pending === 0) {
        if ($isDaemon) {
            sleep(2);
            continue;
        } else {
            echo "[INFO] No hay trabajos pendientes en la cola " . EnqueueDocumentS3Upload::QUEUE_NAME . PHP_EOL;
            break;
        }
    }

    $result = $processor->execute();
    $processedCount++;

    if (($result['status'] ?? '') === 'SUCCESS') {
        $successCount++;
        echo "  [OK] Doc #{$result['document_id']} subido -> {$result['s3_url']}" . PHP_EOL;
    } elseif (($result['status'] ?? '') === 'EMPTY_QUEUE') {
        if (!$isDaemon) break;
    } else {
        $failCount++;
        echo "  [FAIL] {$result['status']}: {$result['message']}" . PHP_EOL;
    }

    if (!$isDaemon && $processedCount >= $limit) {
        echo "[INFO] Límite de {$limit} trabajos alcanzado." . PHP_EOL;
        break;
    }
} while ($isDaemon || $processedCount < $limit);

echo "[{$now}] Finalizado. Procesados: {$processedCount}, Éxito: {$successCount}, Errores: {$failCount}" . PHP_EOL;
