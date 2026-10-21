<?php

namespace App\Domain\Contracts\Repositories;

use App\Domain\Contracts\Entities\Plan;

interface PlanRepositoryInterface
{
    /** @return list<Plan> */
    public function findAll(bool $onlyActive = true): array;

    public function findById(int $id): ?Plan;

    public function findByUuid(string $uuid): ?Plan;

    public function save(Plan $plan): Plan;

    /** @return list<array{id: int, code: string, name: string, is_active: bool}> */
    public function getBanks(bool $onlyActive = true, ?string $search = null): array;
}
