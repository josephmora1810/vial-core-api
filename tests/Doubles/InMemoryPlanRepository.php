<?php

namespace Tests\Doubles;

use App\Domain\Contracts\Entities\Plan;
use App\Domain\Contracts\Repositories\PlanRepositoryInterface;

class InMemoryPlanRepository implements PlanRepositoryInterface
{
    /** @var array<int, Plan> */
    private array $plans = [];
    private int $nextId = 1;

    public function findAll(bool $onlyActive = true): array
    {
        $result = [];
        foreach ($this->plans as $plan) {
            if (!$onlyActive || $plan->isActive()) {
                $result[] = $plan;
            }
        }
        return $result;
    }

    public function findById(int $id): ?Plan
    {
        return $this->plans[$id] ?? null;
    }

    public function findByUuid(string $uuid): ?Plan
    {
        foreach ($this->plans as $plan) {
            if ($plan->getUuid()->getValue() === strtolower(trim($uuid))) {
                return $plan;
            }
        }
        return null;
    }

    public function save(Plan $plan): Plan
    {
        if ($plan->getId() === null) {
            $plan->setId($this->nextId++);
        }
        $this->plans[$plan->getId()] = $plan;
        return $plan;
    }

    public function getBanks(bool $onlyActive = true, ?string $search = null): array
    {
        $banks = [
            ['id' => 1, 'code' => '0114', 'name' => 'Bancaribe', 'is_active' => true],
            ['id' => 2, 'code' => '0102', 'name' => 'Banco de Venezuela', 'is_active' => true],
            ['id' => 3, 'code' => '0104', 'name' => 'Banco Venezolano de Crédito', 'is_active' => true],
        ];
        if ($search !== null && trim($search) !== '') {
            $q = mb_strtolower(trim($search));
            $banks = array_values(array_filter(
                $banks,
                fn(array $b) => str_contains(mb_strtolower($b['name']), $q) || str_contains(mb_strtolower($b['code']), $q)
            ));
        }
        return $banks;
    }
}
