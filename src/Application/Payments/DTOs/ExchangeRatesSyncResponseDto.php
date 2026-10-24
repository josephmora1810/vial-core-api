<?php

namespace App\Application\Payments\DTOs;

readonly class ExchangeRatesSyncResponseDto
{
    /**
     * @param list<ExchangeRateResponseDto> $synced_rates
     */
    public function __construct(public array $synced_rates) {}

    /**
     * @param array<int|string, mixed> $syncedRates Resultado de ExchangeRateServiceInterface::fetchAndSaveTodayRates()
     */
    public static function fromArray(array $syncedRates): self
    {
        $rates = [];
        foreach ($syncedRates as $rate) {
            if (is_array($rate)) {
                $rates[] = ExchangeRateResponseDto::fromArray($rate);
            }
        }

        return new self($rates);
    }

    /**
     * @return array{synced_rates: list<array{currency: string, type: string, rate: float, date: string}>}
     */
    public function toArray(): array
    {
        return [
            'synced_rates' => array_map(static fn(ExchangeRateResponseDto $r): array => $r->toArray(), $this->synced_rates),
        ];
    }
}