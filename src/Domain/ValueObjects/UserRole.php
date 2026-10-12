<?php

namespace App\Domain\ValueObjects;

readonly class UserRole
{
    public const string ADMIN = 'admin';
    public const string DRIVER = 'driver';
    public const string CLIENT = 'client';
    public const string SELLER = 'seller';

    public const array VALID_ROLES = [
        self::ADMIN,
        self::DRIVER,
        self::CLIENT,
        self::SELLER,
    ];

    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));
        $this->ensureIsValidRole($normalized);
        $this->value = $normalized;
    }

    private function ensureIsValidRole(string $role): void
    {
        if (!in_array($role, self::VALID_ROLES, true)) {
            throw new \InvalidArgumentException(sprintf(
                'El rol "%s" no es válido. Los roles permitidos son: %s.',
                $role,
                implode(', ', self::VALID_ROLES)
            ));
        }
    }

    public static function admin(): self
    {
        return new self(self::ADMIN);
    }

    public static function driver(): self
    {
        return new self(self::DRIVER);
    }

    public static function client(): self
    {
        return new self(self::CLIENT);
    }

    public static function seller(): self
    {
        return new self(self::SELLER);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function all(): array
    {
        return self::VALID_ROLES;
    }

    public function isAdmin(): bool
    {
        return $this->value === self::ADMIN;
    }

    public function isDriver(): bool
    {
        return $this->value === self::DRIVER;
    }

    public function isClient(): bool
    {
        return $this->value === self::CLIENT;
    }

    public function isSeller(): bool
    {
        return $this->value === self::SELLER;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(UserRole $other): bool
    {
        return $this->value === $other->getValue();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}