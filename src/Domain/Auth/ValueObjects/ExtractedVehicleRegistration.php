<?php

namespace App\Domain\Auth\ValueObjects;

/**
 * Value Object / DTO que encapsula los datos extraídos de un Certificado de Registro de Vehículo
 * (Carnet de Circulación o Título de Propiedad / NIV) emitido por el INTT vía OCR.
 */
readonly class ExtractedVehicleRegistration
{
    /**
     * @param list<string> $warnings
     * @param array<string, mixed> $rawPayload
     */
    public function __construct(
        private string $documentType,
        private ?string $plateNumber,
        private ?string $brand,
        private ?string $model,
        private ?int $year,
        private ?string $color,
        private ?string $engineSerial,
        private ?string $niv,
        private ?string $vehicleClass = null,
        private ?string $ownerNationalId = null,
        private ?string $ownerName = null,
        private float $confidence = 1.0,
        private array $warnings = [],
        private array $rawPayload = []
    ) {}

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    public function getPlateNumber(): ?string
    {
        return $this->plateNumber;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function getEngineSerial(): ?string
    {
        return $this->engineSerial;
    }

    public function getNiv(): ?string
    {
        return $this->niv;
    }

    public function getVehicleClass(): ?string
    {
        return $this->vehicleClass;
    }

    public function getOwnerNationalId(): ?string
    {
        return $this->ownerNationalId;
    }

    public function getOwnerName(): ?string
    {
        return $this->ownerName;
    }

    public function getConfidence(): float
    {
        return $this->confidence;
    }

    /**
     * @return list<string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRawPayload(): array
    {
        return $this->rawPayload;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'document_type'     => $this->documentType,
            'plate_number'      => $this->plateNumber,
            'brand'             => $this->brand,
            'model'             => $this->model,
            'year'              => $this->year,
            'color'             => $this->color,
            'engine_serial'     => $this->engineSerial,
            'niv'               => $this->niv,
            'vehicle_class'     => $this->vehicleClass,
            'owner_national_id' => $this->ownerNationalId,
            'owner_name'        => $this->ownerName,
            'confidence'        => $this->confidence,
            'warnings'          => $this->warnings,
            'raw_payload'       => $this->rawPayload,
        ];
    }
}
