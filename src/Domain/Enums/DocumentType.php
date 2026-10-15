<?php

namespace App\Domain\Enums;

use InvalidArgumentException;

/**
 * Tipos de documentos admitidos en el sistema de validación KYC y registro.
 */
enum DocumentType: string
{
    case IdentityCard        = 'identity_card';         // Cédula de Identidad venezolana
    case Passport            = 'passport';              // Pasaporte (opcional)
    case VehicleRegistration = 'vehicle_registration';  // Carnet de Circulación
    case NivCertificate      = 'niv_certificate';       // Certificado de Registro de Vehículo / NIV
    case DrivingLicense      = 'driving_license';       // Licencia de Conducir (Grueros)

    public static function fromString(string $value): self
    {
        $type = self::tryFrom(strtolower(trim($value)));
        if ($type === null) {
            throw new InvalidArgumentException(sprintf(
                'El tipo de documento "%s" no es válido. Los tipos permitidos son: %s.',
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
}
