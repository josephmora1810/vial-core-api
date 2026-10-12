<?php

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

readonly class Rif
{
    private const string PATTERN = '/^[VEJGPC]-\d{8,9}$/';

    private string $value;
    private string $prefix;
    private string $number;

    public function __construct(string $value)
    {
        $normalized = $this->normalize($value);
        $this->ensureIsValidRif($normalized);

        $this->value = $normalized;
        $parts = explode('-', $normalized);
        $this->prefix = $parts[0];
        $this->number = $parts[1];
    }

    private function normalize(string $value): string
    {
        $cleaned = strtoupper(trim($value));
        $cleaned = preg_replace('/\s+/', '', $cleaned);

        // Si viene con formato J-12345678-9, lo unificamos a J-123456789
        if (preg_match('/^([VEJGPC])-(\d{7,8})-(\d)$/', $cleaned, $matches)) {
            return $matches[1] . '-' . $matches[2] . $matches[3];
        }

        // Si viene sin guion ej: J123456789
        if (preg_match('/^([VEJGPC])(\d{8,9})$/', $cleaned, $matches)) {
            return $matches[1] . '-' . $matches[2];
        }

        return $cleaned;
    }

    private function ensureIsValidRif(string $rif): void
    {
        if (!preg_match(self::PATTERN, $rif)) {
            throw new InvalidArgumentException(sprintf(
                'El RIF "%s" no es válido. Debe iniciar con V, E, J, G, P o C seguido de guion y 8 o 9 dígitos (ej: J-123456789).',
                $rif
            ));
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function isLegalEntity(): bool
    {
        return in_array($this->prefix, ['J', 'G', 'C'], true);
    }

    public function isNaturalPerson(): bool
    {
        return in_array($this->prefix, ['V', 'E', 'P'], true);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(Rif $other): bool
    {
        return $this->value === $other->getValue();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
