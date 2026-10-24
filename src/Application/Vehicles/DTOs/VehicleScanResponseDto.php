<?php

declare(strict_types=1);

namespace App\Application\Vehicles\DTOs;

readonly class VehicleScanResponseDto
{
    /**
     * @param array<string, mixed> $suggested_vehicle
     * @param list<string> $warnings
     * @param array<string, mixed> $raw_ocr
     */
    public function __construct(
        public array $suggested_vehicle,
        public string $document_type,
        public array $warnings,
        public array $raw_ocr,
        public string $file_path,
        public string $message = 'Documento escaneado. Por favor verifique los campos sugeridos antes de confirmar.'
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            suggested_vehicle: (array)($data['suggested_vehicle'] ?? []),
            document_type: (string)($data['document_type'] ?? 'vehicle_registration'),
            warnings: (array)($data['warnings'] ?? []),
            raw_ocr: (array)($data['raw_ocr'] ?? []),
            file_path: (string)($data['file_path'] ?? ''),
            message: (string)($data['message'] ?? 'Documento escaneado.')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'suggested_vehicle' => $this->suggested_vehicle,
            'document_type'     => $this->document_type,
            'warnings'          => $this->warnings,
            'raw_ocr'           => $this->raw_ocr,
            'file_path'         => $this->file_path,
            'message'           => $this->message,
        ];
    }
}
