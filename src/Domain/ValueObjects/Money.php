<?php

namespace App\Domain\ValueObjects;

use App\Domain\Enums\Currency;
use InvalidArgumentException;

class Money {
    private Currency $currency;

	public function __construct(
		private float $value = 0.00,
        Currency|string $currency = Currency::VES
	) {
		$this->currency = $currency instanceof Currency ? $currency : Currency::fromString($currency);
		$this->ensureAreValidMoney($value);
	}

	private function ensureAreValidMoney(float $value): void
	{
		if ($value < 0) {
			throw new InvalidArgumentException('El valor debe ser mayor o igual a 0.');
		}
	}

    public function getAmount(): float
    {
        return $this->value;
    }

    public function getCurrency(): string
    {
        return $this->currency->value;
    }

    public function getCurrencyEnum(): Currency
    {
        return $this->currency;
    }

    public function add(Money $other): Money
    {
        return new Money($this->value + $other->getAmount(), $this->currency);
    }

    public function subtract(Money $other): Money
    {
        return new Money($this->value - $other->getAmount(), $this->currency);
    }

    public function multiply(float $multiplier): Money
    {
        return new Money($this->value * $multiplier, $this->currency);
    }

    public function divide(float $divisor): Money
    {
        return new Money($this->value / $divisor, $this->currency);
    }

    public function equals(Money $other): bool
    {
        return $this->value === $other->getAmount() && $this->currency === $other->getCurrencyEnum();
    }
}