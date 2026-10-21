<?php

namespace Tests\Doubles;

use App\Domain\Contracts\Entities\ServiceContract;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Contracts\Repositories\ServiceContractRepositoryInterface;

class InMemoryServiceContractRepository implements ServiceContractRepositoryInterface
{
    /** @var array<int, ServiceContract> */
    private array $contracts = [];
    private int $nextId = 1;

    public function findById(int $id): ?ServiceContract
    {
        return $this->contracts[$id] ?? null;
    }

    public function findByUuid(string $uuid): ?ServiceContract
    {
        foreach ($this->contracts as $contract) {
            if ($contract->getUuid()->getValue() === strtolower(trim($uuid))) {
                return $contract;
            }
        }
        return null;
    }

    public function findByClientId(int $clientId): array
    {
        $result = [];
        foreach ($this->contracts as $contract) {
            if ($contract->getClientId() === $clientId) {
                $result[] = $contract;
            }
        }
        return $result;
    }

    public function findBySellerId(int $sellerId, ?string $status = null): array
    {
        $result = [];
        foreach ($this->contracts as $contract) {
            if ($contract->getSellerId() === $sellerId) {
                if ($status === null || trim($status) === '' || $contract->getStatus()->getValue() === strtoupper(trim($status))) {
                    $result[] = $contract;
                }
            }
        }
        return array_reverse($result);
    }

    public function findActiveByVehicleId(int $vehicleId): ?ServiceContract
    {
        foreach ($this->contracts as $contract) {
            if ($contract->getVehicleId() === $vehicleId && $contract->getStatus() === ContractStatus::Active) {
                return $contract;
            }
        }
        return null;
    }

    public function findOverdueActiveContracts(): array
    {
        $overdue = [];
        foreach ($this->contracts as $contract) {
            if ($contract->getStatus() === ContractStatus::Active && !$contract->isActive()) {
                $overdue[] = $contract;
            }
        }
        return $overdue;
    }

    public function expireOverdueContracts(): int
    {
        $count = 0;
        foreach ($this->contracts as $contract) {
            if ($contract->getStatus() === ContractStatus::Active && !$contract->isActive()) {
                $contract->expire();
                $count++;
            }
        }
        return $count;
    }

    public function save(ServiceContract $contract): ServiceContract
    {
        if ($contract->getId() === null) {
            $contract->setId($this->nextId++);
        }
        $this->contracts[$contract->getId()] = $contract;
        return $contract;
    }
}
