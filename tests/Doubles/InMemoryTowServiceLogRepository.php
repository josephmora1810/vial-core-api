<?php

namespace Tests\Doubles;

use App\Domain\Towing\Entities\TowServiceLog;
use App\Domain\Towing\Repositories\TowServiceLogRepositoryInterface;

class InMemoryTowServiceLogRepository implements TowServiceLogRepositoryInterface
{
    /** @var array<int, TowServiceLog> */
    private array $items = [];
    private int $nextId = 1;

    public function append(TowServiceLog $log): void
    {
        $log->setId($this->nextId);
        $this->items[$this->nextId] = $log;
        $this->nextId++;
    }

    public function getLatestByServiceId(int $towServiceId): ?TowServiceLog
    {
        $matching = array_filter($this->items, fn(TowServiceLog $l) => $l->getTowServiceId() === $towServiceId);
        if (empty($matching)) {
            return null;
        }
        return end($matching);
    }

    public function getHistoryByServiceId(int $towServiceId): array
    {
        return array_values(array_filter(
            $this->items,
            fn(TowServiceLog $l) => $l->getTowServiceId() === $towServiceId
        ));
    }
}
