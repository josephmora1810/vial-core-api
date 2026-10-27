<?php

namespace App\Infrastructure\Services;

use App\Domain\Auth\Services\AccountRateLimiterInterface;
use App\Domain\Shared\Services\RedisClientInterface;

/**
 * Adaptador de Infraestructura para control de fuerza bruta en cuentas utilizando Redis (FR-05).
 */
class RedisAccountRateLimiter implements AccountRateLimiterInterface
{
    private const string KEY_PREFIX = 'login_attempts:';
    private const int MAX_FAILED_ATTEMPTS = 5;

    public function __construct(private RedisClientInterface $redis) {}

    public function isLocked(string $identity): bool
    {
        $key = $this->formatKey($identity);
        $attempts = (int)$this->redis->execute('GET', $key);

        return $attempts >= self::MAX_FAILED_ATTEMPTS;
    }

    public function recordFailedAttempt(string $identity, int $lockoutSeconds = 900): void
    {
        $key = $this->formatKey($identity);
        $this->redis->execute('INCR', $key);
        $this->redis->execute('EXPIRE', $key, $lockoutSeconds);
    }

    public function resetAttempts(string $identity): void
    {
        $key = $this->formatKey($identity);
        $this->redis->execute('DEL', $key);
    }

    private function formatKey(string $identity): string
    {
        return self::KEY_PREFIX . md5(strtolower(trim($identity)));
    }
}
