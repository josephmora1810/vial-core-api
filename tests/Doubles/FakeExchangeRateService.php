<?php

namespace Tests\Doubles;

use App\Domain\Payments\Services\ExchangeRateServiceInterface;

class FakeExchangeRateService implements ExchangeRateServiceInterface
{
    public function __construct(
        private float $usdRate = 45.50,
        private float $eurRate = 49.20,
    ) {}

    public function getExchangeRate(string $currency = 'USD', string $type = 'venta'): float
    {
        return strtoupper($currency) === 'EUR' ? $this->eurRate : $this->usdRate;
    }

    public function fetchAndSaveTodayRates(): array
    {
        return $this->getAllCurrentRates();
    }

    public function getAllCurrentRates(): array
    {
        return [
            'USD' => [
                'compra' => $this->usdRate,
                'venta'  => $this->usdRate,
                'date'   => date('Y-m-d'),
            ],
            'EUR' => [
                'compra' => $this->eurRate,
                'venta'  => $this->eurRate,
                'date'   => date('Y-m-d'),
            ],
        ];
    }
}
