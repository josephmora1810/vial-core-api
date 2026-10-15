<?php

namespace App\Application\DTOs;

use App\Domain\Entities\Vehicle;

class VehicleResponseDto
{
    public function __construct(
        public readonly string $id, // UUID v7 público
        public readonly string $plate_number,
        public readonly string $niv,
        public readonly ?string $engine_serial,
        public readonly int $year,
        public readonly string $color,
        public readonly ?string $brand,
        public readonly ?string $model,
        public readonly ?string $vehicle_class,
        public readonly ?string $vehicle_subclass,
        public readonly string $status,
        public readonly bool $is_verified,
        public readonly string $created_at
    ) {}

    public static function fromEntity(
        Vehicle $vehicle,
        ?string $resolvedBrandName = null,
        ?string $resolvedModelName = null,
        ?string $resolvedClassName = null,
        ?string $resolvedSubclassName = null
    ): self {
        return new self(
            id: $vehicle->getUuid()->getValue(),
            plate_number: $vehicle->getPlateNumber()->getValue(),
            niv: $vehicle->getNiv()->getValue(),
            engine_serial: $vehicle->getEngineSerial()?->getValue(),
            year: $vehicle->getYear(),
            color: $vehicle->getColor(),
            brand: $resolvedBrandName ?? $vehicle->getBrandName(),
            model: $resolvedModelName ?? $vehicle->getModelName(),
            vehicle_class: $resolvedClassName,
            vehicle_subclass: $resolvedSubclassName,
            status: $vehicle->getStatus()->getValue(),
            is_verified: $vehicle->isVerified(),
            created_at: $vehicle->getCreatedAt()->format('c')
        );
    }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'plate_number'     => $this->plate_number,
            'niv'              => $this->niv,
            'engine_serial'    => $this->engine_serial,
            'year'             => $this->year,
            'color'            => $this->color,
            'brand'            => $this->brand,
            'model'            => $this->model,
            'vehicle_class'    => $this->vehicle_class,
            'vehicle_subclass' => $this->vehicle_subclass,
            'status'           => $this->status,
            'is_verified'      => $this->is_verified,
            'created_at'       => $this->created_at,
        ];
    }
}
