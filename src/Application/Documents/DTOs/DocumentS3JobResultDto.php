<?php

namespace App\Application\Documents\DTOs;

readonly class DocumentS3JobResultDto
{
    public function __construct(
        public ?bool $enqueued = null,
        public ?bool $processed = null,
        public ?string $status = null,
        public ?string $message = null,
        public ?string $job_id = null,
        public ?string $queue = null,
        public ?string $target_key = null,
        public ?int $document_id = null,
        public ?string $s3_url = null
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromEnqueueResult(array $data): self
    {
        return new self(
            enqueued: (bool)($data['enqueued'] ?? true),
            job_id: isset($data['job_id']) ? (string)$data['job_id'] : null,
            queue: isset($data['queue']) ? (string)$data['queue'] : null,
            target_key: isset($data['target_key']) ? (string)$data['target_key'] : null
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromProcessResult(array $data): self
    {
        return new self(
            processed: (bool)($data['processed'] ?? false),
            status: isset($data['status']) ? (string)$data['status'] : null,
            message: isset($data['message']) ? (string)$data['message'] : null,
            document_id: isset($data['document_id']) ? (int)$data['document_id'] : null,
            s3_url: isset($data['s3_url']) ? (string)$data['s3_url'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'enqueued'    => $this->enqueued,
            'processed'   => $this->processed,
            'status'      => $this->status,
            'message'     => $this->message,
            'job_id'      => $this->job_id,
            'queue'       => $this->queue,
            'target_key'  => $this->target_key,
            'document_id' => $this->document_id,
            's3_url'      => $this->s3_url,
        ], static fn($v) => $v !== null);
    }
}
