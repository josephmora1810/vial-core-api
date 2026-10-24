<?php

namespace App\Application\Towing\UseCases;

use App\Application\Towing\DTOs\TowServiceResponseDto;
use App\Domain\Contracts\Repositories\ServiceContractRepositoryInterface;
use App\Domain\Shared\{
    Services\BroadcasterInterface,
    ValueObjects\Uuid
};
use App\Domain\Towing\{
    Entities\TowService,
    Repositories\TowServiceRepositoryInterface
};
use App\Infrastructure\Database\Redis;
use DomainException;
use Exception;
use LogicException;
use Throwable;

class CompleteTowService
{
    public function __construct(
        private TowServiceRepositoryInterface $towServiceRepository,
        private ?ServiceContractRepositoryInterface $contractRepository = null,
        private ?BroadcasterInterface $broadcaster = null,
        private ?Redis $redis = null
    ) {}

    /**
     * Finaliza el servicio de grúa una vez entregado el vehículo al destino (FR-27).
     * Descuenta el evento y los kilómetros del contrato del cliente si aplica.
     *
     * @param string|int $serviceIdentifier ID o UUID del servicio
     * @param int $driverId ID del chofer autenticado
     * @return array<string, mixed>
     */
    public function execute(string|int $serviceIdentifier, int $driverId): array
    {
        $service = is_numeric($serviceIdentifier)
            ? $this->towServiceRepository->findById((int)$serviceIdentifier)
            : $this->towServiceRepository->findByUuid(Uuid::fromString((string)$serviceIdentifier));

        if ($service === null) {
            throw new Exception('El servicio de asistencia solicitado no existe.', 404);
        }

        if ($service->getDriverId() !== $driverId) {
            throw new DomainException('No estás asignado como conductor de este servicio.', 403);
        }

        try {
            $service->complete();
        } catch (LogicException $e) {
            throw new DomainException($e->getMessage(), 400);
        }

        $this->towServiceRepository->save($service);

        // Descontar del contrato si el servicio estaba amparado por póliza (FR-27)
        if ($service->getContractId() !== null && $this->contractRepository !== null) {
            $contract = $this->contractRepository->findById($service->getContractId());
            if ($contract !== null && $contract->canCoverRescue()) {
                $contract->consumeService($service->getEstimatedDistanceKm());
                $this->contractRepository->save($contract);
            }
        }

        $dto = TowServiceResponseDto::fromEntity($service)->toArray();

        // 1. Notificar en vivo al cliente vía WebSocket seguro (private-tow-{uuid})
        $this->broadcaster?->broadcast(
            channels: "private-tow-{$service->getUuid()->getValue()}",
            event: 'service-completed',
            data: array_merge($dto, [
                'message' => 'Servicio de grúa culminado con éxito. Por favor califica la atención recibida.',
                'can_rate' => true,
            ])
        );

        // 2. Liberar el estado de disponibilidad del chofer en Redis
        if ($this->redis !== null) {
            try {
                $this->redis->execute('SET', "driver:{$driverId}:status", 'available');
            } catch (Throwable) {
                // Silencioso
            }
        }

        return $dto;
    }
}
