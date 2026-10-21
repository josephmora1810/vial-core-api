<?php

namespace App\Domain\Users\ValueObjects;

use InvalidArgumentException;

readonly class Email
{
    public function __construct(private string $value)
    {
        $this->ensureIsValidEmail($value);
    }

    private function ensureIsValidEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(sprintf('El correo "%s" no tiene un formato válido.', $email));
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }

    // El libro dice que los Value Objects se comparan por su valor, no por su identidad.
    public function equals(Email $other): bool
    {
        return $this->value === $other->getValue();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}