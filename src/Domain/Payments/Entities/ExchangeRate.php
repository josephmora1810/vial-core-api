<?php

namespace App\Domain\Payments\Entities;

use App\Domain\Shared\Enums\Currency;
use DateTimeImmutable;
use InvalidArgumentException;

class ExchangeRate
{
    private Currency $currency;
    private string $type;
    private DateTimeImmutable $date;

    public function __construct(
        Currency|string $currency,
        string $type,
        private float $rate,
        DateTimeImmutable|string|null $date = null,
        private ?int $id = null
    ) {
        $this->currency = $currency instanceof Currency ? $currency : Currency::fromString($currency);

        $normalizedType = strtolower(trim($type));
        if (!in_array($normalizedType, ['compra', 'venta'], true)) {
            throw new InvalidArgumentException("El tipo de tasa '{$type}' no es válido. Use 'compra' o 'venta'.");
        }
        $this->type = $normalizedType;

        if ($this->rate <= 0) {
            throw new InvalidArgumentException('El valor de la tasa de cambio debe ser mayor a 0.');
        }

        if ($date instanceof DateTimeImmutable) {
            $this->date = $date;
        } elseif (is_string($date) && trim($date) !== '') {
            $this->date = new DateTimeImmutable($date);
        } else {
            $this->date = new DateTimeImmutable('today');
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getRate(): float
    {
        return $this->rate;
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }
}
