<?php

namespace App\Domain\Users\ValueObjects;

use InvalidArgumentException;

readonly class NationalId
{
    private const string PATTERN = '/^[VE]-\d{6,8}$/';

    private string $value;
    private string $nationality;
    private int $number;

    public function __construct(string $value)
    {
        $normalized = $this->normalize($value);
        $this->ensureIsValidNationalId($normalized);

        $this->value = $normalized;
        $parts = explode('-', $normalized);
        $this->nationality = $parts[0];
        $this->number = (int) $parts[1];
    }

    private function normalize(string $value): string
    {
        $cleaned = strtoupper(trim($value));
        $cleaned = preg_replace('/\s+/', '', $cleaned);

        // Si viene sin guion (ej: V12345678 o E12345678), se le agrega el guion
        if (preg_match('/^([VE])(\d{6,8})$/', $cleaned, $matches)) {
            return $matches[1] . '-' . $matches[2];
        }

        return $cleaned;
    }

    private function ensureIsValidNationalId(string $id): void
    {
        if (!preg_match(self::PATTERN, $id)) {
            throw new InvalidArgumentException(sprintf(
                'La cédula "%s" no es válida. Debe iniciar con V- o E- seguido de entre 6 y 8 dígitos (ej: V-12345678).',
                $id
            ));
        }
    }

    public static function fromParts(string $nationality, int $number): self
    {
        return new self(sprintf('%s-%d', strtoupper(trim($nationality)), $number));
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function getNationality(): string
    {
        return $this->nationality;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function isVenezuelan(): bool
    {
        return $this->nationality === 'V';
    }

    public function isForeigner(): bool
    {
        return $this->nationality === 'E';
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Retorna el formato compacto sin guion requerido por pasarelas bancarias (ej: V12345678).
     */
    public function toCompactFormat(): string
    {
        return $this->nationality . $this->number;
    }

    public function equals(NationalId $other): bool
    {
        return $this->value === $other->getValue();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
