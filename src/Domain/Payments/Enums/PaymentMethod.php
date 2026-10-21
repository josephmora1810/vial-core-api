<?php

namespace App\Domain\Payments\Enums;

use InvalidArgumentException;

enum PaymentMethod: string
{
    case C2p       = 'c2p';
    case PagoMovil = 'pago_movil';

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));
        $method = self::tryFrom($normalized);
        if ($method === null) {
            throw new InvalidArgumentException(sprintf(
                'El método de pago "%s" no es soportado. Métodos permitidos: %s.',
                $value,
                implode(', ', self::values())
            ));
        }
        return $method;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
