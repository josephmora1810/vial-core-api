<?php

namespace App\Infrastructure\Queue;

use App\Domain\Shared\Services\QueueServiceInterface;
use App\Infrastructure\Database\Redis;
use Throwable;

class RedisQueueService implements QueueServiceInterface
{
    public function __construct(
        private Redis $redis
    ) {}

    public function push(string $queue, array $payload): void
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->redis->execute('RPUSH', $queue, $json);
    }

    public function pop(string $queue): ?array
    {
        try {
            $item = $this->redis->execute('LPOP', $queue);
            if ($item === null || $item === false || $item === '') {
                return null;
            }

            $decoded = json_decode((string)$item, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function size(string $queue): int
    {
        try {
            $length = $this->redis->execute('LLEN', $queue);
            return is_numeric($length) ? (int)$length : 0;
        } catch (Throwable) {
            return 0;
        }
    }
}
