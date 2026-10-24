<?php

namespace App\Application\Driver\DTOs;

/**
 * Respuesta tipada de las acciones de turno: iniciar turno, activar grúa y finalizar turno.
 *
 * Los campos opcionales (shift_id, active_crane, started_at, ended_at) solo se serializan cuando
 * están presentes, preservando el contrato JSON histórico de cada acción:
 *  - start / activate: shift_id, driver_id, active_crane, is_on_duty=true, started_at, message
 *  - end (turno cerrado): driver_id, is_on_duty=false, ended_at, message
 *  - end (sin turno activo): driver_id, is_on_duty=false, message
 */
readonly class DriverShiftActionResponseDto
{
    /**
     * @param array{id: string, uuid: string, plate_number: ?string, brand: ?string, model: ?string, color: ?string, year: ?int}|null $activeCrane
     */
    public function __construct(
        public int $driverId,
        public bool $isOnDuty,
        public string $message,
        public ?int $shiftId = null,
        public ?array $activeCrane = null,
        public ?string $startedAt = null,
        public ?string $endedAt = null
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            driverId: (int)($data['driver_id'] ?? 0),
            isOnDuty: (bool)($data['is_on_duty'] ?? false),
            message: (string)($data['message'] ?? ''),
            shiftId: isset($data['shift_id']) ? (int)$data['shift_id'] : null,
            activeCrane: is_array($data['active_crane'] ?? null) ? $data['active_crane'] : null,
            startedAt: isset($data['started_at']) ? (string)$data['started_at'] : null,
            endedAt: isset($data['ended_at']) ? (string)$data['ended_at'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->shiftId !== null) {
            $data['shift_id'] = $this->shiftId;
        }

        $data['driver_id'] = $this->driverId;

        if ($this->activeCrane !== null) {
            $data['active_crane'] = $this->activeCrane;
        }

        $data['is_on_duty'] = $this->isOnDuty;

        if ($this->startedAt !== null) {
            $data['started_at'] = $this->startedAt;
        }

        if ($this->endedAt !== null) {
            $data['ended_at'] = $this->endedAt;
        }

        $data['message'] = $this->message;

        return $data;
    }
}
