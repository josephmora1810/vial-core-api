<?php

declare(strict_types=1);

/**
 * Script para Encolar y Migrar Documentos Existentes en Almacenamiento Local hacia AWS S3
 *
 * Busca en la base de datos todos los documentos cuyo `file_path` sea una ruta local,
 * los encola en Redis (`queue:s3_document_uploads`), y opcionalmente ejecuta el procesador.
 *
 * Uso:
 *   php scripts/enqueue_existing_documents.php           # Encola y procesa de inmediato
 *   php scripts/enqueue_existing_documents.php --only-enqueue # Solo encola en Redis
 */

require_once __DIR__ . '/../autoload.php';

use App\Application\Documents\UseCases\EnqueueDocumentS3Upload;
use App\Application\Documents\UseCases\ProcessDocumentS3UploadJob;
use App\Core\DotEnv;
use App\Domain\Shared\Services\QueueServiceInterface;
use App\Infrastructure\Database\Database;

(new DotEnv(__DIR__ . '/../.env'))->load();

/** @var array<string, mixed> $container */
$container = require __DIR__ . '/../bootstrap/container.php';

/** @var EnqueueDocumentS3Upload $enqueuer */
$enqueuer = $container['services'][EnqueueDocumentS3Upload::class]();
/** @var ProcessDocumentS3UploadJob $processor */
$processor = $container['services'][ProcessDocumentS3UploadJob::class]();
/** @var QueueServiceInterface $queueService */
$queueService = $container['services'][QueueServiceInterface::class]();

$onlyEnqueue = in_array('--only-enqueue', $argv ?? [], true);

$pdo = (new Database())->getConnection();
$stmt = $pdo->query("
    SELECT id, documentable_type, documentable_id, document_type, file_path, status 
    FROM documents 
    WHERE file_path NOT LIKE 'http%'
    ORDER BY id ASC
");
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "== Sincronización de Documentos Locales a AWS S3 ==" . PHP_EOL;
echo "Documentos pendientes por migrar encontrados: " . count($docs) . PHP_EOL . PHP_EOL;

$enqueuedCount = 0;
$skippedCount = 0;

foreach ($docs as $doc) {
    $id = (int)$doc['id'];
    $filePath = (string)$doc['file_path'];
    $docType = (string)$doc['document_type'];
    $entityType = (string)$doc['documentable_type'];
    $entityId = (int)$doc['documentable_id'];

    if (!file_exists($filePath)) {
        // Buscar si existe en storage/documents con solo el nombre base
        $baseName = basename($filePath);
        $alternativePath = dirname(__DIR__) . '/storage/documents/' . $baseName;
        if (file_exists($alternativePath)) {
            $filePath = $alternativePath;
        } else {
            echo "  [SKIP] Doc #{$id}: Archivo físico no encontrado en '{$doc['file_path']}'" . PHP_EOL;
            $skippedCount++;
            continue;
        }
    }

    $res = $enqueuer->execute(
        documentId: $id,
        localFilePath: $filePath,
        documentType: $docType,
        documentableType: $entityType,
        documentableId: $entityId
    );

    echo "  [ENCOLADO] Doc #{$id} ({$docType}) -> Cola S3 [{$res['target_key']}]" . PHP_EOL;
    $enqueuedCount++;
}

echo PHP_EOL . "Total encolados: {$enqueuedCount}, Omitidos (no existen localmente): {$skippedCount}" . PHP_EOL;

if ($onlyEnqueue) {
    echo "Opción --only-enqueue especificada. Los trabajos quedan en Redis para ser procesados por el worker." . PHP_EOL;
    exit(0);
}

if ($enqueuedCount > 0) {
    echo PHP_EOL . "Iniciando procesamiento de subidas a AWS S3..." . PHP_EOL;
    $success = 0;
    $failed = 0;

    while (($job = $queueService->pop(EnqueueDocumentS3Upload::QUEUE_NAME)) !== null) {
        $result = $processor->execute($job);
        if (($result['status'] ?? '') === 'SUCCESS') {
            $success++;
            echo "  [S3 OK] Doc #{$result['document_id']} -> {$result['s3_url']}" . PHP_EOL;
        } else {
            $failed++;
            echo "  [S3 ERROR] Doc #{$result['document_id']}: {$result['message']}" . PHP_EOL;
        }
    }

    echo PHP_EOL . "Resumen de migración: {$success} subidos exitosamente a S3, {$failed} fallidos." . PHP_EOL;
}
