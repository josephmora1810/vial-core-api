<?php

declare(strict_types=1);

namespace App\Domain\Payments\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Value Object para la clave dinámica OTP de transacciones C2P.
 * Garantiza que siempre sea una clave de exactamente 8 dígitos numéricos.
 */
readonly class OtpCode implements Stringable
{
    private const string PATTERN = '/^\d{8}$/';

    private string $value;

    public function __construct(string $value)
    {
        $normalized = preg_replace('/\s+/', '', trim($value)) ?? '';

        if (!preg_match(self::PATTERN, $normalized)) {
            throw new InvalidArgumentException(
                'La clave dinámica OTP no es válida. Debe contener exactamente 8 dígitos numéricos.'
            );
        }

        $this->value = $normalized;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
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
