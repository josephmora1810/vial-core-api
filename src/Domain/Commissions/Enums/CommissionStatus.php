<?php

namespace App\Domain\Commissions\Enums;

use InvalidArgumentException;

enum CommissionStatus: string
{
    case Pending             = 'PENDING';
    case WithdrawalRequested = 'WITHDRAWAL_REQUESTED';
    case Paid                = 'PAID';
    case Cancelled           = 'CANCELLED';

    public static function fromString(string $value): self
    {
        $status = self::tryFrom(strtoupper(trim($value)));
        if ($status === null) {
            throw new InvalidArgumentException(sprintf(
                'El estado de comisión "%s" no es válido. Permitidos: %s.',
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

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isWithdrawalRequested(): bool
    {
        return $this === self::WithdrawalRequested;
    }

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }
}
