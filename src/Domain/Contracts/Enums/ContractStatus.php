<?php

namespace App\Domain\Contracts\Enums;

use InvalidArgumentException;

enum ContractStatus: string
{
    case PendingPayment = 'PENDING_PAYMENT';
    case Active         = 'ACTIVE';
    case Expired        = 'EXPIRED';
    case Cancelled      = 'CANCELLED';

    public static function fromString(string $value): self
    {
        $status = self::tryFrom(strtoupper(trim($value)));
        if ($status === null) {
            throw new InvalidArgumentException(sprintf(
                'El estado de contrato "%s" no es válido. Permitidos: %s.',
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

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
