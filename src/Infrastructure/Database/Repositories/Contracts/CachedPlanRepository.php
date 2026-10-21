<?php

namespace App\Infrastructure\Database\Repositories\Contracts;

use App\Domain\Contracts\Entities\Plan;
use App\Domain\Contracts\Repositories\PlanRepositoryInterface;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Infrastructure\Database\Redis;
use DateTimeImmutable;
use Throwable;

class CachedPlanRepository implements PlanRepositoryInterface
{
    private const CACHE_TTL = 86400; // 24 horas en Redis
    private const KEY_BANKS = 'catalog:banks:active';
    private const KEY_PLANS_ACTIVE = 'catalog:plans:active';
    private const KEY_PLANS_ALL = 'catalog:plans:all';

    public function __construct(
        private PlanRepositoryInterface $inner,
        private ?Redis $redis = null
    ) {}

    public function findAll(bool $onlyActive = true): array
    {
        $cacheKey = $onlyActive ? self::KEY_PLANS_ACTIVE : self::KEY_PLANS_ALL;

        if ($this->redis !== null) {
            try {
                $cached = $this->redis->execute('GET', $cacheKey);
                if (is_string($cached) && $cached !== '') {
                    $decoded = json_decode($cached, true);
                    if (is_array($decoded)) {
                        $plans = [];
                        foreach ($decoded as $row) {
                            $plans[] = $this->hydratePlan($row);
                        }
                        return $plans;
                    }
                }
            } catch (Throwable) {
                // Fallback a MariaDB si Redis no responde
            }
        }

        $plans = $this->inner->findAll($onlyActive);

        if ($this->redis !== null && !empty($plans)) {
            try {
                $serializable = array_map(fn(Plan $p) => $this->serializePlan($p), $plans);
                $this->redis->execute('SETEX', $cacheKey, (string)self::CACHE_TTL, json_encode($serializable));
            } catch (Throwable) {
            }
        }

        return $plans;
    }

    public function findById(int $id): ?Plan
    {
        return $this->inner->findById($id);
    }

    public function findByUuid(string $uuid): ?Plan
    {
        return $this->inner->findByUuid($uuid);
    }

    public function save(Plan $plan): Plan
    {
        $saved = $this->inner->save($plan);
        $this->invalidatePlansCache();
        return $saved;
    }

    public function getBanks(bool $onlyActive = true, ?string $search = null): array
    {
        $banks = null;

        if ($onlyActive && $this->redis !== null) {
            try {
                $cached = $this->redis->execute('GET', self::KEY_BANKS);
                if (is_string($cached) && $cached !== '') {
                    $decoded = json_decode($cached, true);
                    if (is_array($decoded)) {
                        $banks = $decoded;
                    }
                }
            } catch (Throwable) {
                // Fallback a MariaDB si Redis falla
            }
        }

        if ($banks === null) {
            $banks = $this->inner->getBanks($onlyActive, null);

            if ($onlyActive && $this->redis !== null && !empty($banks)) {
                try {
                    $this->redis->execute('SETEX', self::KEY_BANKS, (string)self::CACHE_TTL, json_encode($banks));
                } catch (Throwable) {
                }
            }
        }

        if ($search !== null && trim($search) !== '') {
            $normalizedQuery = $this->normalizeSearch($search);
            $banks = array_values(array_filter(
                $banks,
                fn(array $b) => str_contains($this->normalizeSearch((string)$b['name']), $normalizedQuery)
                    || str_contains(strtolower((string)$b['code']), $normalizedQuery)
            ));
        }

        return $banks;
    }

    public function invalidatePlansCache(): void
    {
        if ($this->redis === null) {
            return;
        }
        try {
            $this->redis->execute('DEL', self::KEY_PLANS_ACTIVE, self::KEY_PLANS_ALL);
        } catch (Throwable) {
        }
    }

    private function normalizeSearch(string $text): string
    {
        $lower = mb_strtolower(trim($text), 'UTF-8');
        return strtr($lower, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePlan(Plan $plan): array
    {
        return [
            'id'              => $plan->getId(),
            'uuid'            => $plan->getUuid()->getValue(),
            'name'            => $plan->getName(),
            'description'     => $plan->getDescription(),
            'price_usd'       => $plan->getPriceUsd()->getAmount(),
            'duration_months' => $plan->getDurationMonths(),
            'max_services'    => $plan->getMaxServices(),
            'max_km'          => $plan->getMaxKm(),
            'is_active'       => $plan->isActive(),
            'created_at'      => $plan->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydratePlan(array $row): Plan
    {
        return new Plan(
            name: (string)$row['name'],
            priceUsd: (float)$row['price_usd'],
            durationMonths: (int)$row['duration_months'],
            maxServices: (int)$row['max_services'],
            maxKm: (float)$row['max_km'],
            description: isset($row['description']) ? (string)$row['description'] : null,
            isActive: (bool)$row['is_active'],
            uuid: new Uuid((string)$row['uuid']),
            id: isset($row['id']) ? (int)$row['id'] : null,
            createdAt: !empty($row['created_at']) ? new DateTimeImmutable((string)$row['created_at']) : null
        );
    }
}
