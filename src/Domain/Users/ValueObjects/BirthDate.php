<?php

namespace App\Domain\Users\ValueObjects;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Value Object para Fecha de Nacimiento con regla de negocio de Mayoría de Edad (18 años).
 */
readonly class BirthDate
{
    private const int MINIMUM_AGE = 18;
    private const int MAXIMUM_AGE = 120;

    private DateTimeImmutable $value;

    public function __construct(DateTimeInterface|string $date)
    {
        $parsed = $this->parseDate($date);
        $this->ensureIsValidBirthDate($parsed);

        $this->value = $parsed->setTime(0, 0, 0);
    }

    private function parseDate(DateTimeInterface|string $date): DateTimeImmutable
    {
        if ($date instanceof DateTimeImmutable) {
            return $date;
        }

        if ($date instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($date);
        }

        $cleaned = trim($date);

        // Soporte para formato venezolano DD/MM/YYYY
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $cleaned, $matches)) {
            $cleaned = sprintf('%04d-%02d-%02d', (int)$matches[3], (int)$matches[2], (int)$matches[1]);
        }

        try {
            return new DateTimeImmutable($cleaned);
        } catch (\Throwable) {
            throw new InvalidArgumentException(sprintf('La fecha de nacimiento "%s" no tiene un formato de fecha válido.', $date));
        }
    }

    private function ensureIsValidBirthDate(DateTimeImmutable $date): void
    {
        $today = new DateTimeImmutable('today');

        if ($date > $today) {
            throw new InvalidArgumentException('La fecha de nacimiento no puede ser una fecha futura.');
        }

        $age = $this->calculateAge($date, $today);

        if ($age < self::MINIMUM_AGE) {
            throw new InvalidArgumentException(sprintf(
                'El usuario debe ser mayor de edad (mínimo %d años). Edad calculada: %d años.',
                self::MINIMUM_AGE,
                $age
            ));
        }

        if ($age > self::MAXIMUM_AGE) {
            throw new InvalidArgumentException(sprintf(
                'La fecha de nacimiento no es válida (supera el límite de %d años).',
                self::MAXIMUM_AGE
            ));
        }
    }

    private function calculateAge(DateTimeImmutable $birthDate, DateTimeImmutable $referenceDate): int
    {
        return $referenceDate->diff($birthDate)->y;
    }

    public static function fromString(string $date): self
    {
        return new self($date);
    }

    public static function fromDateTime(DateTimeInterface $date): self
    {
        return new self($date);
    }

    public function getAge(): int
    {
        return $this->calculateAge($this->value, new DateTimeImmutable('today'));
    }

    public function isAdult(): bool
    {
        return $this->getAge() >= self::MINIMUM_AGE;
    }

    public function getValue(): DateTimeImmutable
    {
        return $this->value;
    }

    public function format(string $format = 'Y-m-d'): string
    {
        return $this->value->format($format);
    }

    public function equals(BirthDate $other): bool
    {
        return $this->format('Y-m-d') === $other->format('Y-m-d');
    }

    public function __toString(): string
    {
        return $this->format('Y-m-d');
    }
}
