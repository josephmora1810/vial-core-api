<?php

namespace App\Domain\Vehicles\ValueObjects;

use InvalidArgumentException;

readonly class EngineSerial
{
    private const string PATTERN = '/^[A-Z0-9]{4,30}$/';

    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper(str_replace([' ', '-'], '', trim($value)));
        $this->ensureIsValidEngineSerial($normalized);
        $this->value = $normalized;
    }

    private function ensureIsValidEngineSerial(string $serial): void
    {
        if (!preg_match(self::PATTERN, $serial)) {
            throw new InvalidArgumentException(sprintf(
                'El serial de motor "%s" no es válido. Debe contener entre 4 y 30 caracteres alfanuméricos.',
                $serial
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

    public function equals(EngineSerial $other): bool
    {
        return $this->value === $other->getValue();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
