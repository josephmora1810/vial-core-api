<?php

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

readonly class Phone
{
    /**
     * E.164 international standard: + followed by 10 to 15 digits
     */
    private const string PATTERN = '/^\+[1-9]\d{9,14}$/';

    private string $value;

    public function __construct(string $value)
    {
        $normalized = $this->normalize($value);
        $this->ensureIsValidPhone($normalized);
        $this->value = $normalized;
    }

    private function normalize(string $value): string
    {
        // Remover espacios, guiones, puntos y paréntesis
        $cleaned = preg_replace('/[^\d+]/', '', trim($value)) ?? '';

        // Si empieza con 00 (formato internacional alternativo), reemplazar por +
        if (str_starts_with($cleaned, '00')) {
            $cleaned = '+' . substr($cleaned, 2);
        }

        // Si empieza con 0 y tiene 11 dígitos (ej: 04122373144), asumir prefijo venezolano +58
        if (preg_match('/^0([1-9]\d{9})$/', $cleaned, $matches)) {
            return '+58' . $matches[1];
        }

        // Si son 10 dígitos venezolanos sin 0 inicial (ej: 4122373144)
        if (preg_match('/^([1-9]\d{9})$/', $cleaned, $matches)) {
            return '+58' . $matches[1];
        }

        // Si empieza con 58 sin el + (ej: 584122373144)
        if (preg_match('/^58(\d{10})$/', $cleaned, $matches)) {
            return '+58' . $matches[1];
        }

        // Si no tiene el signo +, agregarlo al inicio
        if ($cleaned !== '' && !str_starts_with($cleaned, '+')) {
            $cleaned = '+' . $cleaned;
        }

        return $cleaned;
    }

    private function ensureIsValidPhone(string $phone): void
    {
        if (!preg_match(self::PATTERN, $phone)) {
            throw new InvalidArgumentException(sprintf(
                'El número de teléfono "%s" no es válido. Debe tener formato E.164 (ej: +584122373144).',
                $phone
            ));
        }

        // Validación específica para números venezolanos (+58)
        if (str_starts_with($phone, '+58') && strlen($phone) !== 13) {
            throw new InvalidArgumentException(sprintf(
                'El número de teléfono venezolano "%s" debe tener 10 dígitos después del prefijo +58 (ej: +584122373144).',
                $phone
            ));
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function isVenezuelan(): bool
    {
        return str_starts_with($this->value, '+58');
    }

    /**
     * Retorna el código de área / operadora si es venezolano (ej: 412, 414, 424, 212)
     */
    public function getAreaCode(): ?string
    {
        if ($this->isVenezuelan()) {
            return substr($this->value, 3, 3);
        }

        return null;
    }

    /**
     * Retorna el número de abonado sin el código de país
     */
    public function getSubscriberNumber(): string
    {
        if ($this->isVenezuelan()) {
            return substr($this->value, 3);
        }

        return substr($this->value, 1);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(Phone $other): bool
    {
        return $this->value === $other->getValue();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
