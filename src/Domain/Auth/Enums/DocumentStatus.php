<?php

namespace App\Domain\Auth\Enums;

use InvalidArgumentException;

/**
 * Estados del ciclo de vida de validación documental KYC (SRS - Tabla documents).
 */
enum DocumentStatus: string
{
    case Pending  = 'PENDING';
    case Verified = 'VERIFIED';
    case Rejected = 'REJECTED';

    public static function fromString(string $value): self
    {
        $status = self::tryFrom(strtoupper(trim($value)));
        if ($status === null) {
            throw new InvalidArgumentException(sprintf(
                'El estado "%s" no es válido. Los estados permitidos son: %s.',
                $value,
                implode(', ', self::values())
            ));
        }
        return $status;
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isVerified(): bool
    {
        return $this === self::Verified;
    }

    public function isRejected(): bool
    {
        return $this === self::Rejected;
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
