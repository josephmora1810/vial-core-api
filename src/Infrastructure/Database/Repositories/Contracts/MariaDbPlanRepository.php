<?php

namespace App\Infrastructure\Database\Repositories\Contracts;

use App\Domain\Contracts\Entities\Plan;
use App\Domain\Contracts\Repositories\PlanRepositoryInterface;
use App\Domain\Shared\ValueObjects\Uuid;
use DateTimeImmutable;
use PDO;

class MariaDbPlanRepository implements PlanRepositoryInterface
{
    public function __construct(private PDO $db) {}

    public function findAll(bool $onlyActive = true): array
    {
        $sql = "SELECT * FROM plans";
        if ($onlyActive) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY price_usd ASC";

        $stmt = $this->db->query($sql);
        $plans = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $plans[] = $this->mapRow($row);
        }
        return $plans;
    }

    public function findById(int $id): ?Plan
    {
        $stmt = $this->db->prepare("SELECT * FROM plans WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    public function findByUuid(string $uuid): ?Plan
    {
        $stmt = $this->db->prepare("SELECT * FROM plans WHERE uuid = :uuid LIMIT 1");
        $stmt->execute(['uuid' => trim($uuid)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    public function save(Plan $plan): Plan
    {
        if ($plan->getId() === null) {
            $stmt = $this->db->prepare("
                INSERT INTO plans (uuid, name, description, price_usd, duration_months, max_services, max_km, is_active)
                VALUES (:uuid, :name, :description, :price_usd, :duration_months, :max_services, :max_km, :is_active)
            ");
            $stmt->execute([
                'uuid'            => $plan->getUuid()->getValue(),
                'name'            => $plan->getName(),
                'description'     => $plan->getDescription(),
                'price_usd'       => $plan->getPriceUsd()->getAmount(),
                'duration_months' => $plan->getDurationMonths(),
                'max_services'    => $plan->getMaxServices(),
                'max_km'          => $plan->getMaxKm(),
                'is_active'       => $plan->isActive() ? 1 : 0,
            ]);
            $plan->setId((int)$this->db->lastInsertId());
            return $plan;
        }

        $stmt = $this->db->prepare("
            UPDATE plans
            SET name = :name,
                description = :description,
                price_usd = :price_usd,
                duration_months = :duration_months,
                max_services = :max_services,
                max_km = :max_km,
                is_active = :is_active
            WHERE id = :id
        ");
        $stmt->execute([
            'id'              => $plan->getId(),
            'name'            => $plan->getName(),
            'description'     => $plan->getDescription(),
            'price_usd'       => $plan->getPriceUsd()->getAmount(),
            'duration_months' => $plan->getDurationMonths(),
            'max_services'    => $plan->getMaxServices(),
            'max_km'          => $plan->getMaxKm(),
            'is_active'       => $plan->isActive() ? 1 : 0,
        ]);

        return $plan;
    }

    public function getBanks(bool $onlyActive = true, ?string $search = null): array
    {
        $sql = "SELECT id, code, name, is_active FROM banks";
        if ($onlyActive) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY code ASC";

        $stmt = $this->db->query($sql);
        $banks = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $banks[] = [
                'id'        => (int)$row['id'],
                'code'      => (string)$row['code'],
                'name'      => (string)$row['name'],
                'is_active' => (bool)$row['is_active'],
            ];
        }

        if ($search !== null && trim($search) !== '') {
            $q = mb_strtolower(trim($search));
            $banks = array_values(array_filter(
                $banks,
                fn(array $b) => str_contains(mb_strtolower($b['name']), $q) || str_contains(mb_strtolower($b['code']), $q)
            ));
        }

        return $banks;
    }

    private function mapRow(array $row): Plan
    {
        return new Plan(
            name: (string)$row['name'],
            priceUsd: (float)$row['price_usd'],
            durationMonths: (int)$row['duration_months'],
            maxServices: (int)$row['max_services'],
            maxKm: (float)$row['max_km'],
            description: $row['description'] !== null ? (string)$row['description'] : null,
            isActive: (bool)$row['is_active'],
            uuid: new Uuid((string)$row['uuid']),
            id: (int)$row['id'],
            createdAt: !empty($row['created_at']) ? new DateTimeImmutable((string)$row['created_at']) : null
        );
    }
}
