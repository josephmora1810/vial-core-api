<?php

namespace App\Domain\Payments\Services;

interface BalanceGatewayInterface
{
    /**
     * @return array{success: bool, balance: float, accounts?: list<array<string, mixed>>, message?: string, raw_data?: mixed}
     */
    public function getBalance(?string $accountNumber = null, bool $forceRefresh = false): array;
}
