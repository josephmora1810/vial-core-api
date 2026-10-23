<?php

namespace App\Domain\Towing\Enums;

/**
 * Catálogo estandarizado de motivos de cancelación para servicios de auxilio vial (FR-24-B, FR-24-C).
 * Facilita que la app móvil (KMP) despliegue modales de cancelación con un solo toque (One-Tap),
 * y que el backoffice registre métricas analíticas precisas.
 */
enum CancellationReason: string
{
    // Motivos para el Cliente
    case VehicleStarted = 'VEHICLE_STARTED';
    case TowDelay = 'TOW_DELAY';
    case WrongLocation = 'WRONG_LOCATION';
    case ClientOther = 'CLIENT_OTHER';

    // Motivos para el Chofer / Operador
    case DriverBreakdown = 'DRIVER_BREAKDOWN';
    case TrafficBlocked = 'TRAFFIC_BLOCKED';
    case VehicleNotFound = 'VEHICLE_NOT_FOUND';
    case DriverOther = 'DRIVER_OTHER';

    public function description(): string
    {
        return match ($this) {
            self::VehicleStarted   => 'El vehículo logró encender / resolví por mi cuenta',
            self::TowDelay         => 'La grúa tarda demasiado tiempo',
            self::WrongLocation    => 'Me equivoqué al indicar la ubicación de avería',
            self::ClientOther      => 'Otro motivo',

            self::DriverBreakdown  => 'Avería mecánica en la grúa (Reasignar auxilio)',
            self::TrafficBlocked   => 'Tránsito bloqueado / Imposible acceder a la zona',
            self::VehicleNotFound  => 'El vehículo no se encuentra en el lugar indicado',
            self::DriverOther      => 'Otro motivo',
        };
    }

    /**
     * @return list<array{code: string, label: string, reassign: bool}>
     */
    public static function forClient(): array
    {
        return [
            ['code' => self::VehicleStarted->value, 'label' => self::VehicleStarted->description(), 'reassign' => false],
            ['code' => self::TowDelay->value, 'label' => self::TowDelay->description(), 'reassign' => false],
            ['code' => self::WrongLocation->value, 'label' => self::WrongLocation->description(), 'reassign' => false],
            ['code' => self::ClientOther->value, 'label' => self::ClientOther->description(), 'reassign' => false],
        ];
    }

    /**
     * @return list<array{code: string, label: string, reassign: bool}>
     */
    public static function forDriver(): array
    {
        return [
            ['code' => self::DriverBreakdown->value, 'label' => self::DriverBreakdown->description(), 'reassign' => true],
            ['code' => self::TrafficBlocked->value, 'label' => self::TrafficBlocked->description(), 'reassign' => true],
            ['code' => self::VehicleNotFound->value, 'label' => self::VehicleNotFound->description(), 'reassign' => false],
            ['code' => self::DriverOther->value, 'label' => self::DriverOther->description(), 'reassign' => false],
        ];
    }

    /**
     * @return array{client: list<array{code: string, label: string, reassign: bool}>, driver: list<array{code: string, label: string, reassign: bool}>}
     */
    public static function catalog(): array
    {
        return [
            'client' => self::forClient(),
            'driver' => self::forDriver(),
        ];
    }
}
