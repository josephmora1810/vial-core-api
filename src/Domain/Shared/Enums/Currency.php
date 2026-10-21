<?php

namespace App\Domain\Shared\Enums;

use InvalidArgumentException;

/**
 * Monedas soportadas por la plataforma según el SRS (Multimoneda BCV).
 */
enum Currency: string
{
    case VES = 'VES';
    case USD = 'USD';
    case EUR = 'EUR';

    public static function fromString(string $value): self
    {
        $currency = self::tryFrom(strtoupper(trim($value)));
        if ($currency === null) {
            throw new InvalidArgumentException(sprintf(
                'La moneda "%s" no es válida. Las monedas permitidas son: %s.',
                $value,
                implode(', ', self::values())
            ));
        }
        return $currency;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
