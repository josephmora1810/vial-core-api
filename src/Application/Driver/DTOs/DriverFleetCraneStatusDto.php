<?php

namespace App\Application\Driver\DTOs;

/**
 * Estado documental y operativo de una grúa dentro de la flota del operador.
 */
readonly class DriverFleetCraneStatusDto
{
    /**
     * @param array<string, mixed> $crane Representación pública de la grúa (VehicleResponseDto::toArray()).
     */
    public function __construct(
        public array $crane,
        public string $carnetStatus,
        public ?string $carnetFilePath,
        public bool $isOperable
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            crane: is_array($data['crane'] ?? null) ? $data['crane'] : [],
            carnetStatus: (string)($data['carnet_status'] ?? 'NOT_SUBMITTED'),
            carnetFilePath: isset($data['carnet_file_path']) ? (string)$data['carnet_file_path'] : null,
            isOperable: (bool)($data['is_operable'] ?? false)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'crane'            => $this->crane,
            'carnet_status'    => $this->carnetStatus,
            'carnet_file_path' => $this->carnetFilePath,
            'is_operable'      => $this->isOperable,
        ];
    }
}
