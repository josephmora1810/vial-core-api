<?php

namespace App\Application\Vehicles\DTOs;

use App\Domain\Vehicles\Entities\Vehicle;
use DateTimeImmutable;

class VehicleResponseDto
{
    public function __construct(
        public readonly string $uuid, // Identificador público único (nunca ID numérico secuencial)
        public readonly ?string $plate_number,
        public readonly ?string $niv,
        public readonly ?string $engine_serial,
        public readonly int $year,
        public readonly string $color,
        public readonly ?string $brand,
        public readonly ?string $model,
        public readonly ?string $vehicle_class,
        public readonly ?string $vehicle_subclass,
        public readonly string $type,
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
            uuid: $vehicle->getUuid()->getValue(),
            plate_number: $vehicle->getPlateNumber()?->getValue(),
            niv: $vehicle->getNiv()?->getValue(),
            engine_serial: $vehicle->getEngineSerial()?->getValue(),
            year: $vehicle->getYear(),
            color: $vehicle->getColor(),
            brand: $resolvedBrandName ?? $vehicle->getBrandName(),
            model: $resolvedModelName ?? $vehicle->getModelName(),
            vehicle_class: $resolvedClassName,
            vehicle_subclass: $resolvedSubclassName,
            type: $vehicle->getType()->getValue(),
            status: $vehicle->getStatus()->getValue(),
            is_verified: $vehicle->isVerified(),
            created_at: $vehicle->getCreatedAt()->format('c')
        );
    }

    /**
     * Instancia el DTO a partir de una fila enriquecida de vw_vehicle_details.
     * Solo expone el UUID público, nunca el ID primario de la base de datos.
     *
     * @param array<string, mixed> $row
     */
    public static function fromDetailedRow(array $row): self
    {
        $createdAt = !empty($row['created_at'])
            ? (new DateTimeImmutable((string)$row['created_at']))->format('c')
            : (new DateTimeImmutable())->format('c');

        return new self(
            uuid: (string)$row['uuid'],
            plate_number: (string)$row['plate_number'],
            niv: (string)$row['niv'],
            engine_serial: !empty($row['engine_serial']) ? (string)$row['engine_serial'] : null,
            year: (int)$row['year'],
            color: (string)$row['color'],
            brand: !empty($row['brand_name']) ? (string)$row['brand_name'] : null,
            model: !empty($row['model_name']) ? (string)$row['model_name'] : null,
            vehicle_class: !empty($row['class_name']) ? (string)$row['class_name'] : null,
            vehicle_subclass: !empty($row['subclass_name']) ? (string)$row['subclass_name'] : null,
            type: (string)($row['vehicle_type'] ?? 'CAR'),
            status: (string)($row['vehicle_status'] ?? 'ACTIVE'),
            is_verified: (bool)($row['is_verified'] ?? false),
            created_at: $createdAt
        );
    }

    public function toArray(): array
    {
        return [
            'id'               => $this->uuid, // Alias para compatibilidad, siempre contiene el UUID v7
            'uuid'             => $this->uuid,
            'plate_number'     => $this->plate_number,
            'niv'              => $this->niv,
            'engine_serial'    => $this->engine_serial,
            'year'             => $this->year,
            'color'            => $this->color,
            'brand'            => $this->brand,
            'model'            => $this->model,
            'vehicle_class'    => $this->vehicle_class,
            'vehicle_subclass' => $this->vehicle_subclass,
            'type'             => $this->type,
            'status'           => $this->status,
            'is_verified'      => $this->is_verified,
            'created_at'       => $this->created_at,
        ];
    }
}
