<?php

namespace App\Application\Vehicles\DTOs;

use DateTimeImmutable;

class VehicleDetailedResponseDto
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
        public readonly ?string $vehicle_class_code,
        public readonly ?string $vehicle_subclass,
        public readonly string $type,
        public readonly string $status,
        public readonly bool $is_verified,
        public readonly array $owner,
        public readonly ?array $document,
        public readonly string $created_at
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromViewRow(array $row): self
    {
        $createdAt = !empty($row['created_at'])
            ? (new DateTimeImmutable((string)$row['created_at']))->format('c')
            : (new DateTimeImmutable())->format('c');

        $owner = [
            'names'         => (string)($row['owner_names'] ?? ''),
            'surnames'      => (string)($row['owner_surnames'] ?? ''),
            'national_id'   => (string)($row['owner_national_id'] ?? ''),
            'phone'         => (string)($row['owner_phone'] ?? ''),
            'email'         => (string)($row['owner_email'] ?? ''),
        ];

        $document = !empty($row['document_file_path']) ? [
            'document_type' => (string)($row['document_type'] ?? 'vehicle_registration'),
            'file_path'     => (string)$row['document_file_path'],
            'status'        => (string)($row['document_status'] ?? 'PENDING'),
        ] : null;

        return new self(
            uuid: (string)$row['uuid'],
            plate_number: !empty($row['plate_number']) ? (string)$row['plate_number'] : null,
            niv: !empty($row['niv']) ? (string)$row['niv'] : null,
            engine_serial: !empty($row['engine_serial']) ? (string)$row['engine_serial'] : null,
            year: (int)$row['year'],
            color: (string)$row['color'],
            brand: !empty($row['brand_name']) ? (string)$row['brand_name'] : null,
            model: !empty($row['model_name']) ? (string)$row['model_name'] : null,
            vehicle_class: !empty($row['class_name']) ? (string)$row['class_name'] : null,
            vehicle_class_code: !empty($row['class_code']) ? (string)$row['class_code'] : null,
            vehicle_subclass: !empty($row['subclass_name']) ? (string)$row['subclass_name'] : null,
            type: (string)($row['vehicle_type'] ?? 'CAR'),
            status: (string)($row['vehicle_status'] ?? 'ACTIVE'),
            is_verified: (bool)($row['is_verified'] ?? false),
            owner: $owner,
            document: $document,
            created_at: $createdAt
        );
    }

    public function toArray(): array
    {
        return [
            'id'                 => $this->uuid, // Alias para compatibilidad, siempre contiene el UUID v7
            'uuid'               => $this->uuid,
            'plate_number'       => $this->plate_number,
            'niv'                => $this->niv,
            'engine_serial'      => $this->engine_serial,
            'year'               => $this->year,
            'color'              => $this->color,
            'brand'              => $this->brand,
            'model'              => $this->model,
            'vehicle_class'      => $this->vehicle_class,
            'vehicle_class_code' => $this->vehicle_class_code,
            'vehicle_subclass'   => $this->vehicle_subclass,
            'type'               => $this->type,
            'status'             => $this->status,
            'is_verified'        => $this->is_verified,
            'owner'              => $this->owner,
            'document'           => $this->document,
            'created_at'         => $this->created_at,
        ];
    }
}
