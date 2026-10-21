<?php

namespace App\Domain\Contracts\Repositories;

use App\Domain\Contracts\Entities\ServiceContract;

interface ServiceContractRepositoryInterface
{
    public function findById(int $id): ?ServiceContract;

    public function findByUuid(string $uuid): ?ServiceContract;

    public function findByVerificationCode(string $code): ?ServiceContract;

    /** @return list<ServiceContract> */
    public function findByClientId(int $clientId): array;

    /** @return list<ServiceContract> */
    public function findBySellerId(int $sellerId, ?string $status = null): array;

    public function findActiveByVehicleId(int $vehicleId): ?ServiceContract;

    /** @return list<ServiceContract> */
    public function findOverdueActiveContracts(): array;

    public function expireOverdueContracts(): int;

    public function save(ServiceContract $contract): ServiceContract;
}
