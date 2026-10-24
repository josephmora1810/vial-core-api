<?php

declare(strict_types=1);

namespace App\Application\Towing\DTOs;

readonly class SosQuoteResponseDto
{
    /**
     * @param array<string, mixed> $route
     * @param array<string, mixed> $pricing
     */
    public function __construct(
        public bool $can_request_service,
        public int $nearby_cranes_count,
        public float $search_radius_km,
        public array $route,
        public array $pricing,
        public ?string $warning_message = null
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            can_request_service: (bool)($data['can_request_service'] ?? false),
            nearby_cranes_count: (int)($data['nearby_cranes_count'] ?? 0),
            search_radius_km: (float)($data['search_radius_km'] ?? 25.0),
            route: (array)($data['route'] ?? []),
            pricing: (array)($data['pricing'] ?? []),
            warning_message: isset($data['warning_message']) ? (string)$data['warning_message'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'can_request_service' => $this->can_request_service,
            'nearby_cranes_count' => $this->nearby_cranes_count,
            'search_radius_km'    => $this->search_radius_km,
            'route'               => $this->route,
            'pricing'             => $this->pricing,
            'warning_message'     => $this->warning_message,
        ];
    }
}
