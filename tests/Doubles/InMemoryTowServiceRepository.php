<?php

namespace Tests\Doubles;

use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Towing\Entities\TowService;
use App\Domain\Towing\Enums\TowServiceStatus;
use App\Domain\Towing\Repositories\TowServiceRepositoryInterface;

class InMemoryTowServiceRepository implements TowServiceRepositoryInterface
{
    /** @var array<int, TowService> */
    private array $items = [];
    private int $nextId = 1;

    public function save(TowService $service): void
    {
        if ($service->getId() === null) {
            $service->setId($this->nextId);
            $this->items[$this->nextId] = $service;
            $this->nextId++;
        } else {
            $this->items[$service->getId()] = $service;
        }
    }

    public function findById(int $id): ?TowService
    {
        return $this->items[$id] ?? null;
    }

    public function findByUuid(Uuid $uuid): ?TowService
    {
        foreach ($this->items as $service) {
            if ($service->getUuid()->equals($uuid)) {
                return $service;
            }
        }
        return null;
    }

    public function findActiveByClientId(int $clientId): ?TowService
    {
        $activeStatuses = [TowServiceStatus::Pending, TowServiceStatus::InRoute, TowServiceStatus::InProgress];
        foreach (array_reverse($this->items, true) as $service) {
            if ($service->getClientId() === $clientId && in_array($service->getStatus(), $activeStatuses, true)) {
                return $service;
            }
        }
        return null;
    }

    public function findActiveByDriverId(int $driverId): ?TowService
    {
        $activeStatuses = [TowServiceStatus::InRoute, TowServiceStatus::InProgress];
        foreach (array_reverse($this->items, true) as $service) {
            if ($service->getDriverId() === $driverId && in_array($service->getStatus(), $activeStatuses, true)) {
                return $service;
            }
        }
        return null;
    }

    public function lockForUpdate(int $id): ?TowService
    {
        return $this->findById($id);
    }

    public function findPendingNearby(Coordinates $center, float $radiusKm = 25.0): array
    {
        $results = [];
        foreach ($this->items as $service) {
            if ($service->getStatus() === TowServiceStatus::Pending) {
                $d = $this->haversineKm($center, $service->getOrigin());
                if ($d <= $radiusKm) {
                    $results[] = $service;
                }
            }
        }
        return $results;
    }

    /**
     * @return list<TowService>
     */
    public function findOverduePendingServices(int $timeoutMinutes = 10): array
    {
        $now = new \DateTimeImmutable();
        $cutoff = $now->sub(new \DateInterval("PT{$timeoutMinutes}M"));
        $results = [];
        foreach ($this->items as $service) {
            if ($service->getStatus() === TowServiceStatus::Pending) {
                $createdAt = $service->getCreatedAt() ?? new \DateTimeImmutable();
                if ($createdAt <= $cutoff) {
                    $results[] = $service;
                }
            }
        }
        return $results;
    }

    private function haversineKm(Coordinates $c1, Coordinates $c2): float
    {
        $r = 6371.0;
        $lat1 = deg2rad($c1->getLatitude());
        $lon1 = deg2rad($c1->getLongitude());
        $lat2 = deg2rad($c2->getLatitude());
        $lon2 = deg2rad($c2->getLongitude());

        $dLat = $lat2 - $lat1;
        $dLon = $lon2 - $lon1;

        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * (sin($dLon / 2) ** 2);
        return $r * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
