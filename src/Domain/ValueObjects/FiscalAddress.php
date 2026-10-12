<?php

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

readonly class FiscalAddress
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper(preg_replace('/\s+/', ' ', trim($value)) ?? '');
        $this->ensureIsValidFiscalAddress($normalized);
        $this->value = $normalized;
    }

    private function ensureIsValidFiscalAddress(string $address): void
    {
        $length = mb_strlen($address);
        if ($length < 5) {
            throw new InvalidArgumentException('El domicilio fiscal debe tener al menos 5 caracteres.');
        }

        if ($length > 500) {
            throw new InvalidArgumentException('El domicilio fiscal no puede superar los 500 caracteres.');
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(FiscalAddress $other): bool
    {
        return $this->value === $other->getValue();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
