<?php

namespace App\Domain\Payments\Services;

interface ExchangeRateServiceInterface
{
    public function getExchangeRate(string $currency = 'USD', string $type = 'venta'): float;

    /**
     * @return array<string, mixed>
     */
    public function fetchAndSaveTodayRates(): array;

    /**
     * @return array<string, mixed>
     */
    public function getAllCurrentRates(): array;
}
