<?php

namespace App\Application\Contracts\UseCases;

use App\Application\Contracts\DTOs\ServiceContractResponseDto;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Contracts\Repositories\PlanRepositoryInterface;
use App\Domain\Contracts\Repositories\ServiceContractRepositoryInterface;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use Exception;

class CancelServiceContract
{
    public function __construct(
        private ServiceContractRepositoryInterface $contractRepository,
        private PlanRepositoryInterface $planRepository,
        private VehicleRepositoryInterface $vehicleRepository
    ) {}

    /**
     * Cancela un contrato de servicio (pasándolo a CANCELLED para mostrar marca de agua CANCELADO).
     *
     * @return array<string, mixed>
     */
    public function execute(string|int $contractIdentifier, int $requestingUserId, bool $isAdmin = false): array
    {
        $identifier = trim((string)$contractIdentifier);

        $contract = ctype_digit($identifier)
            ? $this->contractRepository->findById((int)$identifier)
            : $this->contractRepository->findByUuid($identifier);

        if ($contract === null) {
            throw new Exception('El contrato especificado no existe.', 404);
        }

        if (!$isAdmin && $contract->getClientId() !== $requestingUserId) {
            throw new Exception('No tienes permiso para cancelar este contrato.', 403);
        }

        if ($contract->getStatus() === ContractStatus::Cancelled) {
            throw new Exception('El contrato ya se encuentra cancelado.', 422);
        }

        $contract->cancel();
        $this->contractRepository->save($contract);

        $plan = $this->planRepository->findById($contract->getPlanId());
        $vehiclePlate = null;
        if ($contract->getVehicleId() !== null) {
            $vehicle = $this->vehicleRepository->findById($contract->getVehicleId());
            $vehiclePlate = $vehicle?->getPlateNumber()->getValue();
        }

        return ServiceContractResponseDto::fromEntity(
            contract: $contract,
            planName: $plan?->getName(),
            vehiclePlate: $vehiclePlate
        )->toArray();
    }
}
