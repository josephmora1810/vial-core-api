<?php

namespace App\Application\Driver\UseCases;

use App\Domain\Driver\Repositories\DriverShiftRepositoryInterface;
use App\Domain\Towing\Repositories\TowServiceRepositoryInterface;
use App\Domain\Users\Enums\UserRole;
use App\Domain\Users\Repositories\UserRepositoryInterface;
use App\Infrastructure\Database\Redis;
use DomainException;
use Exception;
use Throwable;

class EndDriverShift
{
    public function __construct(
        private DriverShiftRepositoryInterface $shiftRepository,
        private UserRepositoryInterface $userRepository,
        private TowServiceRepositoryInterface $towServiceRepository,
        private ?Redis $redis = null
    ) {}

    /**
     * Finaliza el turno de trabajo del chofer y lo retira del radar de disponibilidad.
     * Regla de Negocio: No puede cerrar turno si tiene un servicio de grúa en curso.
     *
     * @param int $driverUserId
     * @return array<string, mixed>
     */
    public function execute(int $driverUserId): array
    {
        $driver = $this->userRepository->findById($driverUserId);
        if ($driver === null) {
            throw new Exception('Conductor no encontrado.', 404);
        }

        if (!$driver->hasRole(UserRole::Driver->getValue()) && !$driver->hasRole(UserRole::Admin->getValue())) {
            throw new Exception('El usuario no posee el rol de conductor u operador de grúas.', 403);
        }

        // Validar que no tenga servicio en curso
        $activeTow = $this->towServiceRepository->findActiveByDriverId($driverUserId);
        if ($activeTow !== null) {
            throw new DomainException('No puedes finalizar tu turno mientras tienes un servicio de grúa en curso.', 409);
        }

        $activeShift = $this->shiftRepository->findActiveByDriverId($driverUserId);
        if ($activeShift === null) {
            return [
                'driver_id'   => $driverUserId,
                'is_on_duty'  => false,
                'message'     => 'No tienes ningún turno activo en este momento.',
            ];
        }

        $this->shiftRepository->endActiveShift($driverUserId);

        // Retirar de Redis
        if ($this->redis !== null) {
            try {
                $this->redis->execute('DEL', "driver:{$driverUserId}:shift");
                $this->redis->execute('ZREM', 'cranes:locations', (string)$driverUserId);
            } catch (Throwable) {
                // Silencioso
            }
        }

        return [
            'driver_id'   => $driverUserId,
            'is_on_duty'  => false,
            'ended_at'    => date('c'),
            'message'     => 'Turno finalizado exitosamente. Has sido retirado del radar de emergencias.',
        ];
    }
}
