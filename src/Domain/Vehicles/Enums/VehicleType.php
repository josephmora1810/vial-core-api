<?php

namespace App\Domain\Vehicles\Enums;

use InvalidArgumentException;

/**
 * Categorías de vehículos del ecosistema UbikGo (SRS pág. 30).
 * Permite distinguir vehículos de clientes de unidades de rescate (CRANE).
 */
enum VehicleType: string
{
    case Car   = 'CAR';
    case Suv   = 'SUV';
    case Moto  = 'MOTO';
    case Crane = 'CRANE';

    public static function fromString(?string $value): self
    {
        if ($value === null || trim($value) === '') {
            return self::Car;
        }

        $normalized = strtoupper(trim($value));
        $type = self::tryFrom($normalized);
        if ($type === null) {
            throw new InvalidArgumentException(sprintf(
                'El tipo de vehículo "%s" no es válido. Los tipos permitidos son: %s.',
                $value,
                implode(', ', self::values())
            ));
        }
        return $type;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isCrane(): bool
    {
        return $this === self::Crane;
    }
}
