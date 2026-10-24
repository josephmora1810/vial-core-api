<?php

namespace App\Application\Driver\DTOs;

/**
 * Respuesta tipada de la carga de la Licencia de Conducir del chofer (FR-09).
 */
readonly class DriverLicenseResponseDto
{
    /**
     * @param array<string, mixed>|null $extractedData
     */
    public function __construct(
        public int $id,
        public string $documentType,
        public ?string $filePath,
        public string $status,
        public ?array $extractedData,
        public string $message
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)($data['id'] ?? 0),
            documentType: (string)($data['document_type'] ?? ''),
            filePath: isset($data['file_path']) ? (string)$data['file_path'] : null,
            status: (string)($data['status'] ?? ''),
            extractedData: is_array($data['extracted_data'] ?? null) ? $data['extracted_data'] : null,
            message: (string)($data['message'] ?? '')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'document_type'  => $this->documentType,
            'file_path'      => $this->filePath,
            'status'         => $this->status,
            'extracted_data' => $this->extractedData,
            'message'        => $this->message,
        ];
    }
}
