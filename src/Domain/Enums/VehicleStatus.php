<?php

namespace App\Domain\Enums;

use InvalidArgumentException;

/**
 * Estados operativos del vehículo (SRS - Flujo de rescate y verificación).
 */
enum VehicleStatus: string
{
    case Pending  = 'PENDING';   // Recién registrado, pendiente de validación de carnet/NIV
    case Active   = 'ACTIVE';    // Verificado y operativo
    case Occupied = 'OCCUPIED';  // En servicio activo de grúa / rescate en curso
    case Disabled = 'DISABLED';  // Inhabilitado temporalmente o suspendido

    public static function fromString(string $value): self
    {
        $status = self::tryFrom(strtoupper(trim($value)));
        if ($status === null) {
            throw new InvalidArgumentException(sprintf(
                'El estado "%s" no es válido. Los estados permitidos son: %s.',
                $value,
                implode(', ', self::values())
            ));
        }
        return $status;
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function isOccupied(): bool
    {
        return $this === self::Occupied;
    }

    public function isDisabled(): bool
    {
        return $this === self::Disabled;
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
}
