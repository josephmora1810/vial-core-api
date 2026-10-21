<?php

namespace App\Application\Documents\UseCases;

use App\Domain\Shared\Services\QueueServiceInterface;
use DateTimeImmutable;

class EnqueueDocumentS3Upload
{
    public const QUEUE_NAME = 'queue:s3_document_uploads';

    public function __construct(
        private QueueServiceInterface $queueService
    ) {}

    public function execute(
        int $documentId,
        string $localFilePath,
        string $documentType,
        string $documentableType,
        int|string $documentableId
    ): array {
        $ext = strtolower(pathinfo($localFilePath, PATHINFO_EXTENSION)) ?: 'jpg';
        $randomSuffix = bin2hex(random_bytes(6));
        $s3Key = sprintf(
            'documents/%s/%s/%s_%s.%s',
            $documentableType,
            $documentableId,
            strtolower($documentType),
            $randomSuffix,
            $ext
        );

        $job = [
            'id'                => bin2hex(random_bytes(8)),
            'type'              => 'upload_document_to_s3',
            'document_id'       => $documentId,
            'local_file_path'   => $localFilePath,
            's3_key'            => $s3Key,
            'attempts'          => 0,
            'max_attempts'      => 3,
            'created_at'        => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        $this->queueService->push(self::QUEUE_NAME, $job);

        return [
            'enqueued'   => true,
            'job_id'     => $job['id'],
            'queue'      => self::QUEUE_NAME,
            'target_key' => $s3Key,
        ];
    }
}
