<?php

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

readonly class Passport
{
    private const string PATTERN = '/^[A-Z0-9]{6,20}$/';

    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper(str_replace([' ', '-'], '', trim($value)));
        $this->ensureIsValidPassport($normalized);
        $this->value = $normalized;
    }

    private function ensureIsValidPassport(string $passport): void
    {
        if (!preg_match(self::PATTERN, $passport)) {
            throw new InvalidArgumentException(sprintf(
                'El pasaporte "%s" no es válido. Debe tener entre 6 y 20 caracteres alfanuméricos.',
                $passport
            ));
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(Passport $other): bool
    {
        return $this->value === $other->getValue();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
