<?php

declare(strict_types=1);

namespace App\Domain\Payments\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Value Object para el código bancario SUDEBAN de 4 dígitos (ej. 0114 Bancaribe, 0102 Banco de Venezuela).
 */
readonly class BankCode implements Stringable
{
    private const string PATTERN = '/^\d{4}$/';
    public const string BANCARIBE = '0114';

    private string $value;

    public function __construct(string $value)
    {
        $normalized = preg_replace('/\D/', '', trim($value)) ?? '';

        if (!preg_match(self::PATTERN, $normalized)) {
            throw new InvalidArgumentException(sprintf(
                'El código de banco "%s" no es válido. Debe constar exactamente de 4 dígitos numéricos SUDEBAN (ej: 0114).',
                $value
            ));
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

    public function isBancaribe(): bool
    {
        return $this->value === self::BANCARIBE;
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
