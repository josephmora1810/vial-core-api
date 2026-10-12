<?php

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

class Money {
	public function __construct(
		private float $value = 0.00,
        private string $currency = 'VES'
	) {
		$this->ensureAreValidMoney($value, $currency);
	}

	private function ensureAreValidMoney(float $value, string $currency): void
	{
		if ($value < 0) {
			throw new InvalidArgumentException('El valor debe ser mayor o igual a 0.');
		}
		if ($currency !== 'VES') {
			throw new InvalidArgumentException('La moneda debe ser VES.');
		}
	}

    public function getAmount(): float
    {
        return $this->value;
    }

    public function getCurrency(): string
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
        return $this->value === $other->getAmount() && $this->currency === $other->getCurrency();
    }
}