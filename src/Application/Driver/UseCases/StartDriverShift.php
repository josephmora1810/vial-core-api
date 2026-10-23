<?php

namespace App\Application\Driver\UseCases;

use App\Domain\Driver\Entities\DriverShift;
use App\Domain\Driver\Repositories\DriverShiftRepositoryInterface;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Towing\Repositories\TowServiceRepositoryInterface;
use App\Domain\Users\Enums\UserRole;
use App\Domain\Users\Repositories\UserRepositoryInterface;
use App\Domain\Vehicles\Enums\VehicleType;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use App\Infrastructure\Database\Redis;
use DomainException;
use Exception;
use Throwable;

class StartDriverShift
{
    public function __construct(
        private DriverShiftRepositoryInterface $shiftRepository,
        private UserRepositoryInterface $userRepository,
        private VehicleRepositoryInterface $vehicleRepository,
        private TowServiceRepositoryInterface $towServiceRepository,
        private ?Redis $redis = null
    ) {}

    /**
     * Inicia el turno de trabajo y activa una grúa específica para el conductor.
     * Regla de Negocio: Un gruero solo puede tener una única grúa activa a la vez.
     * Si ya tiene un servicio de grúa en curso (IN_ROUTE o IN_PROGRESS), se bloquea el cambio.
     *
     * @param int $driverUserId ID del usuario conductor
     * @param string|int $craneIdentifier ID numérico o UUID de la grúa que desea operar
     * @param float|null $initialLatitude Latitud inicial opcional
     * @param float|null $initialLongitude Longitud inicial opcional
     * @return array<string, mixed>
     */
    public function execute(
        int $driverUserId,
        string|int $craneIdentifier,
        ?float $initialLatitude = null,
        ?float $initialLongitude = null
    ): array {
        // 1. Validar que el usuario sea conductor
        $driver = $this->userRepository->findById($driverUserId);
        if ($driver === null) {
            throw new Exception('Conductor no encontrado.', 404);
        }

        if (!$driver->hasRole(UserRole::Driver->getValue()) && !$driver->hasRole(UserRole::Admin->getValue())) {
            throw new Exception('El usuario no posee el rol de conductor u operador de grúas.', 403);
        }

        // 2. Validar que no tenga un servicio en curso (IN_ROUTE o IN_PROGRESS)
        $activeTow = $this->towServiceRepository->findActiveByDriverId($driverUserId);
        if ($activeTow !== null) {
            throw new DomainException('No puedes cambiar de grúa ni reiniciar turno mientras tienes un servicio de auxilio vial en curso.', 409);
        }

        // 3. Resolver la grúa y validar titularidad y aprobación
        $crane = is_numeric($craneIdentifier)
            ? $this->vehicleRepository->findById((int)$craneIdentifier)
            : $this->vehicleRepository->findByUuid(Uuid::fromString((string)$craneIdentifier));

        if ($crane === null) {
            throw new Exception('La grúa indicada no existe.', 404);
        }

        if ($crane->getUserId() !== $driverUserId) {
            throw new DomainException('No tienes autorización para operar una grúa que no pertenece a tu flota.', 403);
        }

        if ($crane->getType() !== VehicleType::Crane) {
            throw new DomainException('El vehículo seleccionado no es una unidad de rescate (CRANE).', 422);
        }

        if (!$crane->isVerified() || !$crane->isActive()) {
            throw new DomainException('Esta grúa aún no ha sido aprobada por la administración o se encuentra inactiva.', 403);
        }

        // 4. Finalizar turno activo previo si existiera (Garantiza estrictamente una sola grúa activa a la vez)
        $this->shiftRepository->endActiveShift($driverUserId);

        // 5. Iniciar nuevo turno con la grúa seleccionada
        $shift = DriverShift::start($driverUserId, (int)$crane->getId());
        if ($initialLatitude !== null && $initialLongitude !== null) {
            $shift->updateCoordinates($initialLatitude, $initialLongitude);
        }
        $shift = $this->shiftRepository->save($shift);

        // 6. Actualizar Redis: Radar geoespacial y estado de disponibilidad en RAM (NFR-14-B)
        if ($this->redis !== null) {
            try {
                $shiftData = json_encode([
                    'driver_id'     => $driverUserId,
                    'crane_id'      => $crane->getId(),
                    'crane_uuid'    => $crane->getUuid()->getValue(),
                    'crane_plate'   => $crane->getPlateNumber()?->getValue(),
                    'is_on_duty'    => true,
                    'is_available'  => true,
                    'started_at'    => $shift->getStartedAt()->format('c'),
                ], JSON_THROW_ON_ERROR);

                $this->redis->execute('SET', "driver:{$driverUserId}:shift", $shiftData);

                if ($initialLatitude !== null && $initialLongitude !== null) {
                    $this->redis->execute(
                        'GEOADD',
                        'cranes:locations',
                        (string)$initialLongitude,
                        (string)$initialLatitude,
                        (string)$driverUserId
                    );
                }
            } catch (Throwable) {
                // Falla silenciosa si Redis no está disponible en el entorno
            }
        }

        return [
            'shift_id'           => $shift->getId(),
            'driver_id'          => $driverUserId,
            'active_crane'       => [
                'id'             => $crane->getUuid()->getValue(),
                'uuid'           => $crane->getUuid()->getValue(),
                'plate_number'   => $crane->getPlateNumber()?->getValue(),
                'brand'          => $crane->getBrandName(),
                'model'          => $crane->getModelName(),
                'color'          => $crane->getColor(),
                'year'           => $crane->getYear(),
            ],
            'is_on_duty'         => true,
            'started_at'         => $shift->getStartedAt()->format('c'),
            'message'            => 'Turno iniciado con éxito. La unidad está activa y disponible en el radar de emergencias.',
        ];
    }
}
