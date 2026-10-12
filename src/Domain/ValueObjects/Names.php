<?php

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

readonly class Names
{
    private const string PATTERN = '/^[\p{L}]+(?:\s+[\p{L}]+)*$/u';

    private string $value;
    /** @var string[] */
    private array $names;

    public function __construct(string $value)
    {
        $normalized = preg_replace('/\s+/', ' ', trim($value)) ?? '';
        $this->ensureIsValidNames($normalized);

        $this->value = $normalized;
        $this->names = explode(' ', $normalized);
    }

    private function ensureIsValidNames(string $names): void
    {
        if (mb_strlen($names) < 2) {
            throw new InvalidArgumentException('El campo de nombres debe tener al menos 2 caracteres.');
        }

        if (mb_strlen($names) > 100) {
            throw new InvalidArgumentException('El campo de nombres no debe exceder los 100 caracteres.');
        }

        if (!preg_match(self::PATTERN, $names)) {
            throw new InvalidArgumentException(sprintf(
                'Los nombres "%s" no son válidos. Solo deben contener letras y estar separados por espacios.',
                $names
            ));
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function getFirstName(): string
    {
        return $this->names[0];
    }

    public function getMiddleNames(): ?string
    {
        if (count($this->names) <= 1) {
            return null;
        }

        return implode(' ', array_slice($this->names, 1));
    }

    /**
     * @return string[]
     */
    public function all(): array
    {
        return $this->names;
    }

    public function count(): int
    {
        return count($this->names);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(Names $other): bool
    {
        return mb_strtolower($this->value) === mb_strtolower($other->getValue());
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
