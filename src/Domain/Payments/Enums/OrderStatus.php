<?php

namespace App\Domain\Payments\Enums;

use InvalidArgumentException;

enum OrderStatus: string
{
    case Pending   = 'PENDING';
    case Paid      = 'PAID';
    case Failed    = 'FAILED';
    case Cancelled = 'CANCELLED';

    public static function fromString(string $value): self
    {
        $status = self::tryFrom(strtoupper(trim($value)));
        if ($status === null) {
            throw new InvalidArgumentException(sprintf(
                'El estado de la orden "%s" no es válido. Permitidos: %s.',
                $value,
                implode(', ', self::values())
            ));
        }
        return $status;
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

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }
}
