<?php

namespace App\Application\Driver\DTOs;

use App\Domain\Driver\Entities\DriverShift;
use App\Domain\Vehicles\Entities\Vehicle;

/**
 * Data Transfer Object para el turno del conductor (Driver Shift).
 * Expone un contrato tipado y predecible para el mapeo a Data Classes en Kotlin Multiplatform.
 */
readonly class DriverShiftResponseDto
{
    /**
     * @param array{id: int, brand: ?string, model: ?string, plate_number: ?string, color: ?string}|null $crane
     * @param array{latitude: float, longitude: float, last_ping_at: ?string}|null $lastLocation
     */
    public function __construct(
        public bool $isActive,
        public ?int $shiftId = null,
        public ?int $craneId = null,
        public ?string $startedAt = null,
        public ?array $crane = null,
        public ?array $lastLocation = null
    ) {}

    public static function inactive(): self
    {
        return new self(
            isActive: false,
            shiftId: null,
            craneId: null,
            startedAt: null,
            crane: null,
            lastLocation: null
        );
    }

    public static function fromEntity(DriverShift $shift, ?Vehicle $crane = null): self
    {
        $craneData = null;
        if ($crane !== null) {
            $craneData = [
                'id'           => $crane->getId(),
                'brand'        => $crane->getBrandName(),
                'model'        => $crane->getModelName(),
                'plate_number' => $crane->getPlateNumber()?->getValue(),
                'color'        => $crane->getColor(),
            ];
        }

        $locationData = null;
        if ($shift->getLastLatitude() !== null && $shift->getLastLongitude() !== null) {
            $locationData = [
                'latitude'     => $shift->getLastLatitude(),
                'longitude'    => $shift->getLastLongitude(),
                'last_ping_at' => $shift->getLastPingAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return new self(
            isActive: $shift->isActive(),
            shiftId: $shift->getId(),
            craneId: $shift->getCraneId(),
            startedAt: $shift->getStartedAt()->format('Y-m-d H:i:s'),
            crane: $craneData,
            lastLocation: $locationData
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'is_active'     => $this->isActive,
            'shift_id'      => $this->shiftId,
            'crane_id'      => $this->craneId,
            'started_at'    => $this->startedAt,
            'crane'         => $this->crane,
            'last_location' => $this->lastLocation,
        ];
    }
}
