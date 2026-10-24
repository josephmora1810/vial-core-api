<?php

namespace App\Application\Driver\DTOs;

/**
 * Respuesta tipada del estado integral de flota y documentación del operador (FR-09, FR-10).
 */
readonly class DriverFleetStatusResponseDto
{
    /**
     * @param list<DriverFleetCraneStatusDto> $cranes
     * @param list<string> $pendingRequirements
     */
    public function __construct(
        public int $driverId,
        public string $driverName,
        public bool $canOperate,
        public bool $isFleetReady,
        public string $identityCardStatus,
        public string $drivingLicenseStatus,
        public int $cranesCount,
        public array $cranes,
        public array $pendingRequirements,
        public string $statusMessage
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $cranes = [];
        foreach (is_array($data['cranes'] ?? null) ? $data['cranes'] : [] as $crane) {
            if (is_array($crane)) {
                $cranes[] = DriverFleetCraneStatusDto::fromArray($crane);
            }
        }

        $pending = is_array($data['pending_requirements'] ?? null)
            ? array_values(array_map('strval', $data['pending_requirements']))
            : [];

        return new self(
            driverId: (int)($data['driver_id'] ?? 0),
            driverName: (string)($data['driver_name'] ?? ''),
            canOperate: (bool)($data['can_operate'] ?? false),
            isFleetReady: (bool)($data['is_fleet_ready'] ?? false),
            identityCardStatus: (string)($data['identity_card_status'] ?? 'NOT_SUBMITTED'),
            drivingLicenseStatus: (string)($data['driving_license_status'] ?? 'NOT_SUBMITTED'),
            cranesCount: (int)($data['cranes_count'] ?? count($cranes)),
            cranes: $cranes,
            pendingRequirements: $pending,
            statusMessage: (string)($data['status_message'] ?? '')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'driver_id'              => $this->driverId,
            'driver_name'            => $this->driverName,
            'can_operate'            => $this->canOperate,
            'is_fleet_ready'         => $this->isFleetReady,
            'identity_card_status'   => $this->identityCardStatus,
            'driving_license_status' => $this->drivingLicenseStatus,
            'cranes_count'           => $this->cranesCount,
            'cranes'                 => array_map(
                static fn(DriverFleetCraneStatusDto $c): array => $c->toArray(),
                $this->cranes
            ),
            'pending_requirements'   => $this->pendingRequirements,
            'status_message'         => $this->statusMessage,
        ];
    }
}
