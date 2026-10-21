<?php

namespace App\Domain\Payments\Repositories;

use DateTimeImmutable;

interface BankTokenRepositoryInterface
{
    /**
     * @return array{gateway: string, access_token: string, token_type: string, expires_at: DateTimeImmutable|null}|null
     */
    public function findByGateway(string $gateway): ?array;

    public function upsert(string $gateway, string $accessToken, string $tokenType, DateTimeImmutable $expiresAt): void;
}
