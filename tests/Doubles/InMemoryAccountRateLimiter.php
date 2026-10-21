<?php

namespace Tests\Doubles;

use App\Domain\Auth\Services\AccountRateLimiterInterface;

class InMemoryAccountRateLimiter implements AccountRateLimiterInterface
{
    /** @var array<string, int> */
    private array $attempts = [];
    private int $maxAttempts;

    public function __construct(int $maxAttempts = 5)
    {
        $this->maxAttempts = $maxAttempts;
    }

    public function isLocked(string $identity): bool
    {
        $norm = $this->normalize($identity);
        return ($this->attempts[$norm] ?? 0) >= $this->maxAttempts;
    }

    public function recordFailedAttempt(string $identity, int $lockoutSeconds = 900): void
    {
        $norm = $this->normalize($identity);
        $this->attempts[$norm] = ($this->attempts[$norm] ?? 0) + 1;
    }

    public function resetAttempts(string $identity): void
    {
        $norm = $this->normalize($identity);
        unset($this->attempts[$norm]);
    }

    public function getAttempts(string $identity): int
    {
        $norm = $this->normalize($identity);
        return $this->attempts[$norm] ?? 0;
    }

    private function normalize(string $identity): string
    {
        return strtolower(trim($identity));
    }
}
