<?php

namespace App\Application\Payments\DTOs;

readonly class ExchangeRatesResponseDto
{
    /**
     * @param list<ExchangeRateResponseDto> $rates
     */
    public function __construct(
        public float $usd_venta,
        public float $usd_compra,
        public array $rates
    ) {}

    /**
     * @param array<string, mixed> $data Resultado de ExchangeRateServiceInterface::getAllCurrentRates()
     */
    public static function fromArray(array $data): self
    {
        $rates = [];
        foreach (is_array($data['rates'] ?? null) ? $data['rates'] : [] as $rate) {
            if (is_array($rate)) {
                $rates[] = ExchangeRateResponseDto::fromArray($rate);
            }
        }

        return new self(
            usd_venta: (float)($data['usd_venta'] ?? 0),
            usd_compra: (float)($data['usd_compra'] ?? 0),
            rates: $rates
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'usd_venta'  => $this->usd_venta,
            'usd_compra' => $this->usd_compra,
            'rates'      => array_map(static fn(ExchangeRateResponseDto $r): array => $r->toArray(), $this->rates),
        ];
    }
}