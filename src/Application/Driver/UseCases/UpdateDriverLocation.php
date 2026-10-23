<?php

namespace App\Application\Driver\UseCases;

use App\Domain\Driver\Repositories\DriverShiftRepositoryInterface;
use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Users\Enums\UserRole;
use App\Domain\Users\Repositories\UserRepositoryInterface;
use App\Infrastructure\Database\Redis;
use DomainException;
use Exception;
use Throwable;

class UpdateDriverLocation
{
    public function __construct(
        private DriverShiftRepositoryInterface $shiftRepository,
        private UserRepositoryInterface $userRepository,
        private ?Redis $redis = null
    ) {}

    /**
     * Emite un latido de geolocalización en segundo plano para el radar geoespacial de grúas disponibles (NFR-14-B).
     * Requiere que el chofer tenga un turno activo y una grúa seleccionada.
     *
     * @param int $driverUserId
     * @param Coordinates $coordinates
     * @param float $speedKmh
     * @return array<string, mixed>
     */
    public function execute(
        int $driverUserId,
        Coordinates $coordinates,
        float $speedKmh = 0.0
    ): array {
        $driver = $this->userRepository->findById($driverUserId);
        if ($driver === null) {
            throw new Exception('Conductor no encontrado.', 404);
        }

        if (!$driver->hasRole(UserRole::Driver->getValue()) && !$driver->hasRole(UserRole::Admin->getValue())) {
            throw new Exception('El usuario no posee el rol de conductor u operador de grúas.', 403);
        }

        $activeShift = $this->shiftRepository->findActiveByDriverId($driverUserId);
        if ($activeShift === null) {
            throw new DomainException('Debes iniciar turno y seleccionar una grúa activa antes de emitir telemetría para el radar.', 400);
        }

        $lat = $coordinates->getLatitude();
        $lng = $coordinates->getLongitude();

        // 1. Actualizar en MariaDB
        $this->shiftRepository->updateLocation($driverUserId, $lat, $lng);

        // 2. Actualizar en Redis GEO (GEOADD cranes:locations {lng} {lat} {driver_id})
        if ($this->redis !== null) {
            try {
                $this->redis->execute(
                    'GEOADD',
                    'cranes:locations',
                    (string)$lng,
                    (string)$lat,
                    (string)$driverUserId
                );

                $locData = json_encode([
                    'latitude'   => $lat,
                    'longitude'  => $lng,
                    'speed_kmh'  => $speedKmh,
                    'updated_at' => date('c'),
                ], JSON_THROW_ON_ERROR);

                $this->redis->execute('SET', "driver:{$driverUserId}:location", $locData);
            } catch (Throwable) {
                // Silencioso
            }
        }

        return [
            'status'      => 'success',
            'driver_id'   => $driverUserId,
            'crane_id'    => $activeShift->getCraneId(),
            'coordinates' => [
                'latitude'  => $lat,
                'longitude' => $lng,
            ],
            'speed_kmh'   => $speedKmh,
            'recorded_at' => date('c'),
        ];
    }
}
