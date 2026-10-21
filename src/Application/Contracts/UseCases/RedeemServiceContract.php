<?php

namespace App\Application\Contracts\UseCases;

use App\Application\Contracts\DTOs\ServiceContractResponseDto;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Contracts\Repositories\PlanRepositoryInterface;
use App\Domain\Contracts\Repositories\ServiceContractRepositoryInterface;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use Exception;
use InvalidArgumentException;

class RedeemServiceContract
{
    public function __construct(
        private ServiceContractRepositoryInterface $contractRepository,
        private PlanRepositoryInterface $planRepository,
        private VehicleRepositoryInterface $vehicleRepository
    ) {}

    /**
     * Canjea un servicio de grúa sobre un Contrato de Servicio activo,
     * descontando 1 servicio disponible y los kilómetros recorridos.
     *
     * @return array<string, mixed>
     */
    public function execute(
        string|int $contractIdentifier,
        int $actorUserId,
        float $distanceKm = 0.0,
        bool $isSellerOrAdmin = false
    ): array {
        $identifier = trim((string)$contractIdentifier);

        $contract = ctype_digit($identifier)
            ? $this->contractRepository->findById((int)$identifier)
            : $this->contractRepository->findByUuid($identifier);

        if ($contract === null) {
            throw new Exception('El contrato solicitado no existe.', 404);
        }

        if ($contract->getClientId() !== $actorUserId && !$isSellerOrAdmin) {
            throw new Exception('No tienes permiso para canjear servicios de este contrato.', 403);
        }

        // Si el contrato ya superó su timestamp de expiración, marcarlo como EXPIRED
        if ($contract->getStatus() === ContractStatus::Active && !$contract->isActive()) {
            $contract->expire();
            $this->contractRepository->save($contract);
            throw new Exception('El contrato ha expirado y debe ser renovado antes de canjear un servicio.', 422);
        }

        try {
            $contract->consumeService($distanceKm);
        } catch (InvalidArgumentException $e) {
            throw new Exception($e->getMessage(), 422);
        }

        $contract = $this->contractRepository->save($contract);

        $plan = $this->planRepository->findById($contract->getPlanId());
        $vehiclePlate = null;
        if ($contract->getVehicleId() !== null) {
            $vehicle = $this->vehicleRepository->findById($contract->getVehicleId());
            $vehiclePlate = $vehicle?->getPlateNumber()->getValue();
        }

        return [
            'contract'          => ServiceContractResponseDto::fromEntity($contract, $plan?->getName(), $vehiclePlate)->toArray(),
            'redeemed_services' => 1,
            'redeemed_km'       => max(0.0, round($distanceKm, 2)),
        ];
    }
}
