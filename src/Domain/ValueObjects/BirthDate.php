<?php

namespace App\Domain\ValueObjects;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

readonly class BirthDate
{
    private DateTimeImmutable $value;

    public function __construct(string|DateTimeInterface $value)
    {
        if ($value instanceof DateTimeInterface) {
            $date = DateTimeImmutable::createFromInterface($value);
        } else {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', trim($value))
                ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', trim($value))
                ?: date_create_immutable(trim($value));

            if ($parsed === false) {
                throw new InvalidArgumentException(sprintf(
                    'La fecha de nacimiento "%s" no es válida. Use el formato Y-m-d.',
                    $value
                ));
            }
            $date = $parsed;
        }

        $this->ensureIsValidBirthDate($date);
        $this->value = $date;
    }

    private function ensureIsValidBirthDate(DateTimeImmutable $date): void
    {
        $today = new DateTimeImmutable('today');
        if ($date > $today) {
            throw new InvalidArgumentException('La fecha de nacimiento no puede ser una fecha futura.');
        }

        $minDate = $today->modify('-130 years');
        if ($date < $minDate) {
            throw new InvalidArgumentException('La fecha de nacimiento no puede ser anterior a 130 años.');
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function fromDateTime(DateTimeInterface $dateTime): self
    {
        
        return new self($dateTime);
    }

    public function toDateTime(): DateTimeImmutable
    {
        return $this->value;
    }

    public function getAge(?DateTimeImmutable $asOf = null): int
    {
        $target = $asOf ?? new DateTimeImmutable('today');
        return $this->value->diff($target)->y;
    }

    public function isAdult(int $minimumAge = 18): bool
    {
        return $this->getAge() >= $minimumAge;
    }

    public function getValue(): string
    {
        return $this->value->format('Y-m-d');
    }

    public function equals(BirthDate $other): bool
    {
        return $this->getValue() === $other->getValue();
    }

    public function __toString(): string
    {
        return $this->getValue();
    }
}
