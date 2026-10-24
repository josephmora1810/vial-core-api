<?php

namespace App\Application\Driver\DTOs;

/**
 * Respuesta tipada del registro de una grúa (CRANE) por parte de un operador (FR-09).
 */
readonly class DriverCraneRegistrationResponseDto
{
    /**
     * @param array<string, mixed> $crane Representación pública de la grúa (VehicleResponseDto::toArray()).
     */
    public function __construct(
        public array $crane,
        public ?int $carnetDocumentId,
        public ?int $licenseDocumentId,
        public bool $isFleetReady,
        public string $statusMessage
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            crane: is_array($data['crane'] ?? null) ? $data['crane'] : [],
            carnetDocumentId: isset($data['carnet_document_id']) ? (int)$data['carnet_document_id'] : null,
            licenseDocumentId: isset($data['license_document_id']) ? (int)$data['license_document_id'] : null,
            isFleetReady: (bool)($data['is_fleet_ready'] ?? false),
            statusMessage: (string)($data['status_message'] ?? '')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'crane'               => $this->crane,
            'carnet_document_id'  => $this->carnetDocumentId,
            'license_document_id' => $this->licenseDocumentId,
            'is_fleet_ready'      => $this->isFleetReady,
            'status_message'      => $this->statusMessage,
        ];
    }
}
