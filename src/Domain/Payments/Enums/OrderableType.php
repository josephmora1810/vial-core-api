<?php

namespace App\Domain\Payments\Enums;

use InvalidArgumentException;

enum OrderableType: string
{
    case ServiceContract = 'service_contract';
    case TowService      = 'tow_service';

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));
        if ($normalized === 'servicecontract' || str_ends_with($normalized, 'servicecontract')) {
            return self::ServiceContract;
        }
        if ($normalized === 'towservice' || str_ends_with($normalized, 'towservice')) {
            return self::TowService;
        }

        $type = self::tryFrom($normalized);
        if ($type === null) {
            throw new InvalidArgumentException(sprintf(
                'El tipo de entidad pagable "%s" no es válido. Permitidos: %s.',
                $value,
                implode(', ', self::values())
            ));
        }
        return $type;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
