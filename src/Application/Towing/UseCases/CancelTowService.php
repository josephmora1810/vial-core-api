<?php

namespace App\Application\Towing\UseCases;

use App\Application\Towing\DTOs\TowServiceResponseDto;
use App\Domain\Shared\Services\BroadcasterInterface;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Towing\Entities\TowService;
use App\Domain\Towing\Enums\TowServiceStatus;
use App\Domain\Towing\Repositories\TowServiceRepositoryInterface;
use DomainException;
use Exception;
use LogicException;

class CancelTowService
{
    public function __construct(
        private TowServiceRepositoryInterface $towServiceRepository,
        private ?BroadcasterInterface $broadcaster = null
    ) {}

    /**
     * Cancela o reasigna un servicio de grúa en curso o pendiente (FR-24-B, FR-24-C).
     *
     * @param string|int $serviceIdentifier ID o UUID del servicio
     * @param int $userId ID del usuario que solicita la acción
     * @param string $reason Motivo de la cancelación o avería
     * @param string $actor 'client' | 'driver' | 'admin' | 'system'
     * @param bool $reassign Si es true y el chofer cancela por avería, vuelve a estado PENDING (FR-24-B)
     * @return array<string, mixed>
     */
    public function execute(
        string|int $serviceIdentifier,
        int $userId,
        string $reason,
        string $actor = 'client',
        bool $reassign = false
    ): array {
        $service = is_numeric($serviceIdentifier)
            ? $this->towServiceRepository->findById((int)$serviceIdentifier)
            : $this->towServiceRepository->findByUuid(Uuid::fromString((string)$serviceIdentifier));

        if ($service === null) {
            throw new Exception('El servicio de asistencia solicitado no existe.', 404);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Debe indicar un motivo de cancelación.', 400);
        }

        $normalizedActor = strtolower(trim($actor));

        try {
            if ($normalizedActor === 'driver') {
                if ($service->getDriverId() !== $userId) {
                    throw new DomainException('No estás asignado como conductor de este servicio.', 403);
                }

                if ($reassign && $service->getStatus() === TowServiceStatus::InRoute) {
                    $service->reopenForReassignment($reason);
                    $this->towServiceRepository->save($service);

                    $dto = TowServiceResponseDto::fromEntity($service)->toArray();

                    // Reanudar búsqueda en el radar seguro de grúas (FR-24-B)
                    $this->broadcaster?->broadcast('private-cranes-radar', 'new-sos-request', $dto);
                    $this->broadcaster?->broadcast("private-tow-{$service->getUuid()->getValue()}", 'driver-reassigned', ['reason' => $reason]);

                    return $dto;
                }

                $service->cancelByDriver($reason);
            } elseif ($normalizedActor === 'client') {
                if ($service->getClientId() !== $userId) {
                    throw new DomainException('No eres el titular de este servicio de grúa.', 403);
                }
                $service->cancelByClient($reason);
            } elseif ($normalizedActor === 'system' || $normalizedActor === 'timeout') {
                $service->cancelByTimeout();
            } else {
                if ($service->getClientId() === $userId) {
                    $service->cancelByClient($reason);
                } elseif ($service->getDriverId() === $userId) {
                    $service->cancelByDriver($reason);
                } else {
                    throw new DomainException('No tienes autorización para cancelar este servicio.', 403);
                }
            }
        } catch (LogicException|DomainException $e) {
            throw new DomainException($e->getMessage(), 400);
        }

        $this->towServiceRepository->save($service);

        $dto = TowServiceResponseDto::fromEntity($service)->toArray();

        // Notificar cancelación en el canal privado
        $this->broadcaster?->broadcast("private-tow-{$service->getUuid()->getValue()}", 'service-cancelled', $dto);

        return $dto;
    }
}
