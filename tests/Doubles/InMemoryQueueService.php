<?php

namespace Tests\Doubles;

use App\Domain\Shared\Services\QueueServiceInterface;

class InMemoryQueueService implements QueueServiceInterface
{
    /** @var array<string, list<array<string, mixed>>> */
    public array $queues = [];

    public function push(string $queue, array $payload): void
    {
        $this->queues[$queue][] = $payload;
    }

    public function pop(string $queue): ?array
    {
        if (empty($this->queues[$queue])) {
            return null;
        }

        return array_shift($this->queues[$queue]);
    }

    public function size(string $queue): int
    {
        return isset($this->queues[$queue]) ? count($this->queues[$queue]) : 0;
    }

    public function clear(): void
    {
        $this->queues = [];
    }
}
