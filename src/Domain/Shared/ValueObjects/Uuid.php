<?php

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

readonly class Uuid
{
    private const string PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));
        $this->ensureIsValidUuidV7($normalized);
        $this->value = $normalized;
    }

    private function ensureIsValidUuidV7(string $uuid): void
    {
        if (!preg_match(self::PATTERN, $uuid)) {
            throw new InvalidArgumentException(sprintf(
                'El UUID "%s" no es válido. Debe ser un UUIDv7 con formato xxxxxxxx-xxxx-7xxx-[89ab]xxx-xxxxxxxxxxxx.',
                $uuid
            ));
        }
    }

    /**
     * Genera un nuevo UUIDv7 compatible con RFC 9562
     */
    public static function generate(): self
    {
        $timeMs = (int) floor(microtime(true) * 1000);
        $timeHex = str_pad(dechex($timeMs), 12, '0', STR_PAD_LEFT);

        $randBytesA = random_bytes(2);
        $randA = substr(bin2hex($randBytesA), 0, 3);

        $randBytesB = random_bytes(8);
        $variantByte = (ord($randBytesB[0]) & 0x3f) | 0x80;
        $variantAndNext = sprintf('%02x%02x', $variantByte, ord($randBytesB[1]));
        $randB = bin2hex(substr($randBytesB, 2, 6));

        $uuid = sprintf(
            '%s-%s-7%s-%s-%s',
            substr($timeHex, 0, 8),
            substr($timeHex, 8, 4),
            $randA,
            $variantAndNext,
            $randB
        );

        return new self($uuid);
    }

    public static function v7(): self
    {
        return self::generate();
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(Uuid $other): bool
    {
        return $this->value === $other->getValue();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
