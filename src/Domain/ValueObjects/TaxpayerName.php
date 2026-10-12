<?php

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

readonly class TaxpayerName
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper(preg_replace('/\s+/', ' ', trim($value)) ?? '');
        $this->ensureIsValidTaxpayerName($normalized);
        $this->value = $normalized;
    }

    private function ensureIsValidTaxpayerName(string $name): void
    {
        $length = mb_strlen($name);
        if ($length < 2) {
            throw new InvalidArgumentException('El nombre o razón social del contribuyente debe tener al menos 2 caracteres.');
        }

        if ($length > 255) {
            throw new InvalidArgumentException('El nombre o razón social del contribuyente no puede superar los 255 caracteres.');
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

    public function equals(TaxpayerName $other): bool
    {
        return $this->value === $other->getValue();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
