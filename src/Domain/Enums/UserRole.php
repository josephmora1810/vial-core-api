<?php

namespace App\Domain\Enums;

use InvalidArgumentException;

/**
 * Roles del sistema según la Especificación de Requerimientos de Software (SRS).
 */
enum UserRole: string
{
    case Admin  = 'admin';
    case Driver = 'driver';
    case Client = 'client';
    case Seller = 'seller';

    public static function fromString(string $value): self
    {
        $role = self::tryFrom(strtolower(trim($value)));
        if ($role === null) {
            throw new InvalidArgumentException(sprintf(
                'El rol "%s" no es válido. Los roles permitidos son: %s.',
                $value,
                implode(', ', self::values())
            ));
        }
        return $role;
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function isDriver(): bool
    {
        return $this === self::Driver;
    }

    public function isClient(): bool
    {
        return $this === self::Client;
    }

    public function isSeller(): bool
    {
        return $this === self::Seller;
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
