<?php

namespace App\Application\Documents\UseCases;

use App\Domain\Auth\Repositories\DocumentRepositoryInterface;
use App\Domain\Shared\{
    Repositories\ImageRepositoryInterface,
    Services\IntegrationLoggerInterface,
    Services\QueueServiceInterface,
    Services\StorageServiceInterface
};
use Throwable;

class ProcessDocumentS3UploadJob
{
    public const FAILED_QUEUE_NAME = 'queue:s3_failed_uploads';

    public function __construct(
        private QueueServiceInterface $queueService,
        private StorageServiceInterface $storageService,
        private DocumentRepositoryInterface $documentRepository,
        private ?IntegrationLoggerInterface $logger = null,
        private ?ImageRepositoryInterface $imageRepository = null
    ) {}

    /**
     * Procesa un trabajo extraído de la cola de subidas a S3.
     * Si no se pasa $job, lo extrae automáticamente con pop().
     *
     * @param array<string, mixed>|null $job
     * @return array{processed: bool, status: string, message: string, document_id?: int, s3_url?: string}
     */
    public function execute(?array $job = null): array
    {
        if ($job === null) {
            $job = $this->queueService->pop(EnqueueDocumentS3Upload::QUEUE_NAME);
        }

        if ($job === null) {
            return [
                'processed' => false,
                'status'    => 'EMPTY_QUEUE',
                'message'   => 'No hay trabajos pendientes en la cola de S3.',
            ];
        }

        $jobId         = (string)($job['id'] ?? 'unknown');
        $documentId    = (int)($job['document_id'] ?? 0);
        $localFilePath = (string)($job['local_file_path'] ?? '');
        $s3Key         = (string)($job['s3_key'] ?? '');
        $attempts      = (int)($job['attempts'] ?? 0);
        $maxAttempts   = (int)($job['max_attempts'] ?? 3);

        // 1. Validar existencia del archivo local
        if (!file_exists($localFilePath) || !is_readable($localFilePath)) {
            $errorMsg = "Archivo local inaccesible para trabajo S3 [{$jobId}]: {$localFilePath}";
            $this->logJobFailure($job, $errorMsg);
            $this->queueService->push(self::FAILED_QUEUE_NAME, array_merge($job, ['fatal_error' => $errorMsg]));

            return [
                'processed'   => true,
                'status'      => 'FILE_NOT_FOUND',
                'message'     => $errorMsg,
                'document_id' => $documentId,
            ];
        }

        // 2. Limpieza de metadatos EXIF/GPS para privacidad (NFR-17) e intento de subida a S3
        try {
            $this->stripExifMetadata($localFilePath);
            $s3Url = $this->storageService->upload($localFilePath, $s3Key);

            // Eliminar archivo local temporal tras subida exitosa a AWS S3
            if (file_exists($localFilePath) && is_file($localFilePath)) {
                @unlink($localFilePath);
            }

            // 3. Actualizar la entidad Document en MariaDB
            $document = $this->documentRepository->findById($documentId);
            if ($document !== null) {
                $document->setFilePath($s3Url);
                $this->documentRepository->save($document);

                // 4. Registrar URL en la tabla polimórfica de imágenes (1 a 1 para el documento)
                if ($this->imageRepository !== null) {
                    $this->imageRepository->save(
                        imageableType: 'document',
                        imageableId: $documentId,
                        type: $document->getDocumentType()->getValue(),
                        path: $s3Url
                    );
                }
            }

            return [
                'processed'   => true,
                'status'      => 'SUCCESS',
                'message'     => "Documento #{$documentId} subido con éxito a S3.",
                'document_id' => $documentId,
                's3_url'      => $s3Url,
            ];
        } catch (Throwable $e) {
            $attempts++;
            $job['attempts'] = $attempts;
            $job['last_error'] = $e->getMessage();

            $this->logJobFailure($job, $e->getMessage());

            if ($attempts < $maxAttempts) {
                // Reintentar encolando nuevamente
                $this->queueService->push(EnqueueDocumentS3Upload::QUEUE_NAME, $job);
                return [
                    'processed'   => true,
                    'status'      => 'RETRY_QUEUED',
                    'message'     => "Error en intento {$attempts}/{$maxAttempts}: {$e->getMessage()}. Reencolado.",
                    'document_id' => $documentId,
                ];
            }

            // Excedió reintentos: enviar a cola de fallidos para inspección
            $this->queueService->push(self::FAILED_QUEUE_NAME, $job);

            return [
                'processed'   => true,
                'status'      => 'MAX_ATTEMPTS_EXCEEDED',
                'message'     => "Trabajo fallido definitivamente tras {$attempts} intentos: {$e->getMessage()}",
                'document_id' => $documentId,
            ];
        }
    }

    private function logJobFailure(array $job, string $error): void
    {
        $this->logger?->log(
            service: 's3_queue',
            operation: 'job_failure',
            url: $job['s3_key'] ?? 'unknown',
            statusCode: 500,
            durationMs: 0.0,
            requestPayload: $job,
            responsePayload: null,
            error: $error
        );
    }

    /**
     * Elimina metadatos EXIF y coordenadas GPS de fotografías cargadas (NFR-17).
     */
    private function stripExifMetadata(string $filePath): void
    {
        if (!function_exists('imagecreatefromjpeg') || !is_file($filePath)) {
            return;
        }

        $mime = @mime_content_type($filePath);
        if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
            $image = @imagecreatefromjpeg($filePath);
            if ($image !== false) {
                // Re-encodifica la imagen sin metadatos EXIF ni cabeceras GPS pasivas
                @imagejpeg($image, $filePath, 92);
                unset($image);
            }
        }
    }
}
