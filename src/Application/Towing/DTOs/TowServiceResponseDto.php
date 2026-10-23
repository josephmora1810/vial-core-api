<?php

namespace App\Application\Towing\DTOs;

use App\Domain\Towing\Entities\TowService;

readonly class TowServiceResponseDto
{
    /**
     * @param array{latitude: float, longitude: float} $origin
     * @param array{latitude: float, longitude: float} $destination
     */
    public function __construct(
        public string $id, // UUID v7 público
        public array $origin,
        public string $origin_address,
        public array $destination,
        public string $destination_address,
        public string $failure_description,
        public float $estimated_distance_km,
        public float $extra_km,
        public string $status,
        public ?string $vehicle_plate = null,
        public ?string $contract_code = null,
        public ?string $extra_order_id = null,
        public ?string $cancellation_reason = null,
        public ?string $cancelled_by = null,
        public ?string $accepted_at = null,
        public ?string $arrived_at = null,
        public ?string $completed_at = null,
        public ?string $cancelled_at = null,
        public ?string $created_at = null,
        public ?string $updated_at = null
    ) {}

    public static function fromEntity(
        TowService $service,
        ?string $vehiclePlate = null,
        ?string $contractCode = null,
        ?string $extraOrderUuid = null
    ): self {
        return new self(
            id: $service->getUuid()->getValue(),
            origin: [
                'latitude'  => $service->getOrigin()->getLatitude(),
                'longitude' => $service->getOrigin()->getLongitude(),
            ],
            origin_address: $service->getOriginAddress(),
            destination: [
                'latitude'  => $service->getDestination()->getLatitude(),
                'longitude' => $service->getDestination()->getLongitude(),
            ],
            destination_address: $service->getDestinationAddress(),
            failure_description: $service->getFailureDescription(),
            estimated_distance_km: $service->getEstimatedDistanceKm(),
            extra_km: $service->getExtraKm(),
            status: $service->getStatus()->value,
            vehicle_plate: $vehiclePlate,
            contract_code: $contractCode,
            extra_order_id: $extraOrderUuid,
            cancellation_reason: $service->getCancellationReason(),
            cancelled_by: $service->getCancelledBy(),
            accepted_at: $service->getAcceptedAt()?->format('Y-m-d H:i:s'),
            arrived_at: $service->getArrivedAt()?->format('Y-m-d H:i:s'),
            completed_at: $service->getCompletedAt()?->format('Y-m-d H:i:s'),
            cancelled_at: $service->getCancelledAt()?->format('Y-m-d H:i:s'),
            created_at: $service->getCreatedAt()?->format('Y-m-d H:i:s'),
            updated_at: $service->getUpdatedAt()?->format('Y-m-d H:i:s')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'                    => $this->id,
            'origin'                => $this->origin,
            'origin_address'        => $this->origin_address,
            'destination'           => $this->destination,
            'destination_address'   => $this->destination_address,
            'failure_description'   => $this->failure_description,
            'estimated_distance_km' => $this->estimated_distance_km,
            'extra_km'              => $this->extra_km,
            'status'                => $this->status,
            'vehicle_plate'         => $this->vehicle_plate,
            'contract_code'         => $this->contract_code,
            'extra_order_id'        => $this->extra_order_id,
            'cancellation_reason'   => $this->cancellation_reason,
            'cancelled_by'          => $this->cancelled_by,
            'accepted_at'           => $this->accepted_at,
            'arrived_at'            => $this->arrived_at,
            'completed_at'          => $this->completed_at,
            'cancelled_at'          => $this->cancelled_at,
            'created_at'            => $this->created_at,
            'updated_at'            => $this->updated_at,
        ];
    }
}
