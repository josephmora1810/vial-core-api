<?php

namespace App\Application\Contracts\UseCases;

use App\Application\Contracts\DTOs\ServiceContractResponseDto;
use App\Application\Payments\DTOs\OrderResponseDto;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Contracts\Repositories\PlanRepositoryInterface;
use App\Domain\Contracts\Repositories\ServiceContractRepositoryInterface;
use App\Domain\Payments\Enums\OrderableType;
use App\Domain\Payments\Repositories\OrderRepositoryInterface;
use App\Domain\Users\Repositories\UserRepositoryInterface;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;

class ListSellerContracts
{
    public function __construct(
        private ServiceContractRepositoryInterface $contractRepository,
        private PlanRepositoryInterface $planRepository,
        private UserRepositoryInterface $userRepository,
        private VehicleRepositoryInterface $vehicleRepository,
        private OrderRepositoryInterface $orderRepository
    ) {}

    /**
     * Lista los contratos gestionados por una vendedora, permitiendo filtrar por estado
     * (ej. ?status=EXPIRED para gestionar renovaciones, ?status=ACTIVE para comisiones, o ?status=PENDING_PAYMENT).
     *
     * @return list<array<string, mixed>>
     */
    public function execute(int $sellerId, ?string $statusFilter = null): array
    {
        // Antes de listar, asegurar que cualquier contrato vencido del vendedor pase a EXPIRED
        $contracts = $this->contractRepository->findBySellerId($sellerId, null);
        foreach ($contracts as $c) {
            if ($c->getStatus() === ContractStatus::Active && !$c->isActive()) {
                $c->expire();
                $this->contractRepository->save($c);
            }
        }

        if ($statusFilter !== null && trim($statusFilter) !== '') {
            $normalizedFilter = strtoupper(trim($statusFilter));
            $contracts = array_values(array_filter(
                $contracts,
                fn($c) => $c->getStatus()->getValue() === $normalizedFilter
            ));
        }

        $result = [];
        foreach ($contracts as $contract) {
            $plan = $this->planRepository->findById($contract->getPlanId());
            $client = $this->userRepository->findById($contract->getClientId());
            $vehicle = $contract->getVehicleId() !== null
                ? $this->vehicleRepository->findById($contract->getVehicleId())
                : null;

            $dto = ServiceContractResponseDto::fromEntity(
                $contract,
                $plan?->getName(),
                $vehicle?->getPlateNumber()->getValue()
            )->toArray();

            $dto['pdf_download_url'] = '/api/v1/contracts/' . $contract->getUuid()->getValue() . '/pdf';
            $dto['client_name'] = $client?->getFullName();
            $dto['client_identification_card'] = $client?->getIdentificationCard()->getValue();
            $dto['client_phone'] = $client?->getPhone()->getValue();

            // Adjuntar la orden más reciente asociada a este contrato (inicial o de renovación)
            $orders = $contract->getId() !== null
                ? $this->orderRepository->findByOrderable(OrderableType::ServiceContract->getValue(), $contract->getId())
                : [];

            $dto['latest_order'] = !empty($orders)
                ? OrderResponseDto::fromEntity($orders[0])->toArray()
                : null;

            $result[] = $dto;
        }

        return $result;
    }
}
