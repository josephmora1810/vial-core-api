<?php

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

readonly class Surnames
{
    private const string PATTERN = '/^[\p{L}]+(?:[\s\-\'][\p{L}]+)*$/u';

    private string $value;
    /** @var string[] */
    private array $lastNames;

    public function __construct(string $value)
    {
        $normalized = preg_replace('/\s+/', ' ', trim($value)) ?? '';
        $this->ensureIsValidLastNames($normalized);

        $this->value = $normalized;
        $this->lastNames = explode(' ', $normalized);
    }

    private function ensureIsValidLastNames(string $lastNames): void
    {
        if (mb_strlen($lastNames) < 2) {
            throw new InvalidArgumentException('El campo de apellidos debe tener al menos 2 caracteres.');
        }

        if (mb_strlen($lastNames) > 100) {
            throw new InvalidArgumentException('El campo de apellidos no debe exceder los 100 caracteres.');
        }

        if (!preg_match(self::PATTERN, $lastNames)) {
            throw new InvalidArgumentException(sprintf(
                'Los apellidos "%s" no son válidos. Solo deben contener letras y estar separados por espacios.',
                $lastNames
            ));
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function getFirstLastName(): string
    {
        return $this->lastNames[0];
    }

    public function getSecondLastName(): ?string
    {
        if (count($this->lastNames) <= 1) {
            return null;
        }

        return implode(' ', array_slice($this->lastNames, 1));
    }

    /**
     * @return string[]
     */
    public function all(): array
    {
        return $this->lastNames;
    }

    public function count(): int
    {
        return count($this->lastNames);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(Surnames $other): bool
    {
        return mb_strtolower($this->value) === mb_strtolower($other->getValue());
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
