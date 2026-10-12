<?php

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

readonly class Niv
{
    private const string PATTERN = '/^[A-Z0-9]{17}$/';

    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper(str_replace([' ', '-'], '', trim($value)));
        $this->ensureIsValidNiv($normalized);
        $this->value = $normalized;
    }

    private function ensureIsValidNiv(string $niv): void
    {
        if (!preg_match(self::PATTERN, $niv)) {
            throw new InvalidArgumentException(sprintf(
                'El N.I.V. "%s" no es válido. Debe tener exactamente 17 caracteres alfanuméricos.',
                $niv
            ));
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * WMI (World Manufacturer Identifier) - Primeros 3 caracteres
     */
    public function getWmi(): string
    {
        return substr($this->value, 0, 3);
    }

    /**
     * VDS (Vehicle Descriptor Section) - Caracteres 4 al 9
     */
    public function getVds(): string
    {
        return substr($this->value, 3, 6);
    }

    /**
     * VIS (Vehicle Identifier Section) - Caracteres 10 al 17
     */
    public function getVis(): string
    {
        return substr($this->value, 9, 8);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(Niv $other): bool
    {
        return $this->value === $other->getValue();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
