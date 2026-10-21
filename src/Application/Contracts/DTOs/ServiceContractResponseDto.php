<?php

namespace App\Application\Contracts\DTOs;

use App\Domain\Contracts\Entities\ServiceContract;

readonly class ServiceContractResponseDto
{
    public function __construct(
        public string $id,
        public int $client_id,
        public ?int $seller_id,
        public int $plan_id,
        public ?string $plan_name,
        public ?int $vehicle_id,
        public ?string $vehicle_plate,
        public int $remaining_services,
        public float $remaining_km,
        public string $status,
        public bool $is_active,
        public ?string $starts_at,
        public ?string $expires_at,
        public string $created_at
    ) {}

    public static function fromEntity(
        ServiceContract $contract,
        ?string $planName = null,
        ?string $vehiclePlate = null
    ): self {
        return new self(
            id: $contract->getUuid()->getValue(),
            client_id: $contract->getClientId(),
            seller_id: $contract->getSellerId(),
            plan_id: $contract->getPlanId(),
            plan_name: $planName,
            vehicle_id: $contract->getVehicleId(),
            vehicle_plate: $vehiclePlate,
            remaining_services: $contract->getRemainingServices(),
            remaining_km: $contract->getRemainingKm(),
            status: $contract->getStatus()->getValue(),
            is_active: $contract->isActive(),
            starts_at: $contract->getStartsAt()?->format('Y-m-d H:i:s'),
            expires_at: $contract->getExpiresAt()?->format('Y-m-d H:i:s'),
            created_at: $contract->getCreatedAt()->format('Y-m-d H:i:s')
        );
    }

    public function toArray(): array
    {
        return [
            'id'                 => $this->id,
            'client_id'          => $this->client_id,
            'seller_id'          => $this->seller_id,
            'plan_id'            => $this->plan_id,
            'plan_name'          => $this->plan_name,
            'vehicle_id'         => $this->vehicle_id,
            'vehicle_plate'      => $this->vehicle_plate,
            'remaining_services' => $this->remaining_services,
            'remaining_km'       => $this->remaining_km,
            'status'             => $this->status,
            'is_active'          => $this->is_active,
            'starts_at'          => $this->starts_at,
            'expires_at'         => $this->expires_at,
            'created_at'         => $this->created_at,
        ];
    }
}
