<?php

declare(strict_types=1);

namespace App\Application\Towing\DTOs;

readonly class TelemetryLoggedResponseDto
{
    /**
     * @param array{latitude: float, longitude: float} $coordinates
     */
    public function __construct(
        public string $status,
        public array $coordinates,
        public float $speed_kmh,
        public ?string $recorded_at = null
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: (string)($data['status'] ?? 'logged'),
            coordinates: (array)($data['coordinates'] ?? []),
            speed_kmh: (float)($data['speed_kmh'] ?? 0.0),
            recorded_at: isset($data['recorded_at']) ? (string)$data['recorded_at'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status'      => $this->status,
            'coordinates' => $this->coordinates,
            'speed_kmh'   => $this->speed_kmh,
            'recorded_at' => $this->recorded_at,
        ];
    }
}
