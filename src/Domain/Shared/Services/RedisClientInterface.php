<?php

declare(strict_types=1);

namespace App\Domain\Shared\Services;

interface RedisClientInterface
{
    public function execute(mixed ...$args): mixed;
}
