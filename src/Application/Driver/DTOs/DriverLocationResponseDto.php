<?php

namespace App\Application\Driver\DTOs;

/**
 * Respuesta tipada del latido de ubicación del chofer para el radar geoespacial (NFR-14-B).
 */
readonly class DriverLocationResponseDto
{
    public function __construct(
        public string $status,
        public int $driverId,
        public int $craneId,
        public float $latitude,
        public float $longitude,
        public float $speedKmh,
        public string $recordedAt
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $coordinates = is_array($data['coordinates'] ?? null) ? $data['coordinates'] : [];

        return new self(
            status: (string)($data['status'] ?? 'success'),
            driverId: (int)($data['driver_id'] ?? 0),
            craneId: (int)($data['crane_id'] ?? 0),
            latitude: (float)($coordinates['latitude'] ?? 0.0),
            longitude: (float)($coordinates['longitude'] ?? 0.0),
            speedKmh: (float)($data['speed_kmh'] ?? 0.0),
            recordedAt: (string)($data['recorded_at'] ?? '')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status'      => $this->status,
            'driver_id'   => $this->driverId,
            'crane_id'    => $this->craneId,
            'coordinates' => [
                'latitude'  => $this->latitude,
                'longitude' => $this->longitude,
            ],
            'speed_kmh'   => $this->speedKmh,
            'recorded_at' => $this->recordedAt,
        ];
    }
}
