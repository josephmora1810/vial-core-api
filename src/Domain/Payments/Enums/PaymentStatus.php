<?php

namespace App\Domain\Payments\Enums;

use InvalidArgumentException;

enum PaymentStatus: string
{
    case Pending   = 'pending';
    case Completed = 'completed';
    case Failed    = 'failed';

    public static function fromString(string $value): self
    {
        $status = self::tryFrom(strtolower(trim($value)));
        if ($status === null) {
            throw new InvalidArgumentException(sprintf(
                'El estado de pago "%s" no es válido. Permitidos: %s.',
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
}
