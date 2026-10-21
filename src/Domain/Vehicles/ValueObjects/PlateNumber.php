<?php

namespace App\Domain\Vehicles\ValueObjects;

use InvalidArgumentException;

readonly class PlateNumber
{
    private const string PATTERN = '/^[A-Z]{2}\d{3}[A-Z]{2}$/';

    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper(str_replace([' ', '-'], '', trim($value)));
        $this->ensureIsValidPlateNumber($normalized);
        $this->value = $normalized;
    }

    private function ensureIsValidPlateNumber(string $plate): void
    {
        if (!preg_match(self::PATTERN, $plate)) {
            throw new InvalidArgumentException(sprintf(
                'La placa "%s" no es válida. Debe tener el formato venezolano de 7 caracteres (ej: AB123CD).',
                $plate
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

    public function equals(PlateNumber $other): bool
    {
        return $this->value === $other->getValue();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
