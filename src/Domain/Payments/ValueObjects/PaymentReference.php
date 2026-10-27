<?php

declare(strict_types=1);

namespace App\Domain\Payments\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Value Object para la referencia bancaria (Pago Móvil / Operaciones Plus).
 * Garantiza que siempre se utilicen los últimos 8 dígitos numéricos requeridos por Bancaribe.
 */
readonly class PaymentReference implements Stringable
{
    private string $value;
    private string $rawValue;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new InvalidArgumentException('La referencia bancaria no puede estar vacía.');
        }

        $this->rawValue = $trimmed;
        $this->value = $this->normalize($trimmed);
    }

    private function normalize(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if ($digits === '') {
            throw new InvalidArgumentException(sprintf(
                'La referencia bancaria "%s" no contiene dígitos numéricos válidos.',
                $value
            ));
        }

        // Bancaribe (Operaciones Plus) requiere obligatoriamente los últimos 8 dígitos de la referencia
        if (strlen($digits) > 8) {
            return substr($digits, -8);
        }

        return $digits;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getRawValue(): string
    {
        return $this->rawValue;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
