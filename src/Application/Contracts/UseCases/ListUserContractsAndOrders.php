<?php

namespace App\Application\Contracts\UseCases;

use App\Application\Payments\DTOs\OrderResponseDto;
use App\Application\Contracts\DTOs\ServiceContractResponseDto;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Payments\Repositories\OrderRepositoryInterface;
use App\Domain\Contracts\Repositories\PlanRepositoryInterface;
use App\Domain\Contracts\Repositories\ServiceContractRepositoryInterface;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use App\Domain\Payments\Services\ExchangeRateServiceInterface;

class ListUserContractsAndOrders
{
    public function __construct(
        private ServiceContractRepositoryInterface $contractRepository,
        private OrderRepositoryInterface $orderRepository,
        private PlanRepositoryInterface $planRepository,
        private VehicleRepositoryInterface $vehicleRepository,
        private ?ExchangeRateServiceInterface $exchangeRateService = null
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listContracts(int $userId): array
    {
        $contracts = $this->contractRepository->findByClientId($userId);
        $result = [];

        foreach ($contracts as $contract) {
            if ($contract->getStatus() === ContractStatus::Active && !$contract->isActive()) {
                $contract->expire();
                $this->contractRepository->save($contract);
            }
            $plan = $this->planRepository->findById($contract->getPlanId());
            $vehiclePlate = null;
            if ($contract->getVehicleId() !== null) {
                $vehicle = $this->vehicleRepository->findById($contract->getVehicleId());
                $vehiclePlate = $vehicle?->getPlateNumber()->getValue();
            }

            $result[] = ServiceContractResponseDto::fromEntity(
                contract: $contract,
                planName: $plan?->getName(),
                vehiclePlate: $vehiclePlate
            )->toArray();
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listOrders(int $userId): array
    {
        $orders = $this->orderRepository->findByUserId($userId);
        $currentRate = $this->exchangeRateService?->getExchangeRate('USD', 'venta');
        $result = [];

        foreach ($orders as $order) {
            if ($currentRate !== null && !$order->isPaid() && $order->refreshExchangeRate($currentRate)) {
                $this->orderRepository->save($order);
            }
            $result[] = OrderResponseDto::fromEntity($order)->toArray();
        }

        return $result;
    }
}
