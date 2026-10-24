<?php

namespace App\Application\Documents\DTOs;

use App\Domain\Auth\Entities\Document;

readonly class DocumentResponseDto
{
    /**
     * @param array<string, mixed>|null $extracted_data
     */
    public function __construct(
        public ?int $id,
        public string $documentable_type,
        public int $documentable_id,
        public string $document_type,
        public string $status,
        public ?string $file_path = null,
        public ?array $extracted_data = null,
        public ?bool $vehicle_verified = null,
        public ?string $message = null,
        public ?string $created_at = null,
        public ?string $updated_at = null
    ) {}

    public static function fromEntity(
        Document $doc,
        ?bool $vehicleVerified = null,
        ?string $message = null
    ): self {
        return new self(
            id: $doc->getId(),
            documentable_type: $doc->getDocumentableType(),
            documentable_id: $doc->getDocumentableId(),
            document_type: $doc->getDocumentType()->getValue(),
            status: $doc->getStatus()->getValue(),
            file_path: $doc->getFilePath(),
            extracted_data: $doc->getExtractedData(),
            vehicle_verified: $vehicleVerified,
            message: $message,
            created_at: $doc->getCreatedAt()->format('Y-m-d H:i:s'),
            updated_at: $doc->getUpdatedAt()?->format('Y-m-d H:i:s')
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            documentable_type: (string)($data['documentable_type'] ?? 'user'),
            documentable_id: (int)($data['documentable_id'] ?? 0),
            document_type: (string)($data['document_type'] ?? ''),
            status: (string)($data['status'] ?? 'PENDING'),
            file_path: isset($data['file_path']) ? (string)$data['file_path'] : null,
            extracted_data: is_array($data['extracted_data'] ?? null) ? $data['extracted_data'] : null,
            vehicle_verified: array_key_exists('vehicle_verified', $data) && $data['vehicle_verified'] !== null
                ? (bool)$data['vehicle_verified']
                : null,
            message: isset($data['message']) ? (string)$data['message'] : null,
            created_at: isset($data['created_at']) ? (string)$data['created_at'] : null,
            updated_at: isset($data['updated_at']) ? (string)$data['updated_at'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'id'                => $this->id,
            'documentable_type' => $this->documentable_type,
            'documentable_id'   => $this->documentable_id,
            'document_type'     => $this->document_type,
            'file_path'         => $this->file_path,
            'status'            => $this->status,
            'extracted_data'    => $this->extracted_data,
            'vehicle_verified'  => $this->vehicle_verified,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];

        if ($this->message !== null) {
            $payload['message'] = $this->message;
        }

        return $payload;
    }
}
