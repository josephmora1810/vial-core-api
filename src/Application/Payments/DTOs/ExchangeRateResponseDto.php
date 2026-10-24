<?php

namespace App\Application\Payments\DTOs;

use App\Domain\Payments\Entities\ExchangeRate;

readonly class ExchangeRateResponseDto
{
    public function __construct(
        public string $currency,
        public string $type,
        public float $rate,
        public string $date
    ) {}

    public static function fromEntity(ExchangeRate $exchangeRate): self
    {
        return new self(
            currency: $exchangeRate->getCurrency()->getValue(),
            type: $exchangeRate->getType(),
            rate: $exchangeRate->getRate(),
            date: $exchangeRate->getDate()->format('Y-m-d')
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            currency: (string)($data['currency'] ?? ''),
            type: (string)($data['type'] ?? ''),
            rate: (float)($data['rate'] ?? 0),
            date: (string)($data['date'] ?? '')
        );
    }

    /**
     * @return array{currency: string, type: string, rate: float, date: string}
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'type'     => $this->type,
            'rate'     => $this->rate,
            'date'     => $this->date,
        ];
    }
}