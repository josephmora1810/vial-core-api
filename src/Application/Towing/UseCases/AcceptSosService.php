<?php

namespace App\Application\Towing\UseCases;

use App\Application\Towing\DTOs\TowServiceResponseDto;
use App\Domain\Payments\Repositories\OrderRepositoryInterface;
use App\Domain\Shared\Services\BroadcasterInterface;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Towing\Entities\TowService;
use App\Domain\Towing\Enums\TowServiceStatus;
use App\Domain\Towing\Repositories\TowServiceRepositoryInterface;
use DomainException;
use Exception;

class AcceptSosService
{
    public function __construct(
        private TowServiceRepositoryInterface $towServiceRepository,
        private ?BroadcasterInterface $broadcaster = null,
        private ?OrderRepositoryInterface $orderRepository = null
    ) {}

    /**
     * Adjudica un servicio de auxilio (SOS) a un conductor de grúa (FR-24, NFR-11-B).
     *
     * @param string|int $serviceIdentifier ID o UUID del servicio
     * @param int $driverId ID del conductor autenticado
     * @return array<string, mixed>
     */
    public function execute(string|int $serviceIdentifier, int $driverId): array
    {
        // 1. Verificar si el conductor ya tiene un servicio en curso
        $activeService = $this->towServiceRepository->findActiveByDriverId($driverId);
        if ($activeService !== null) {
            throw new DomainException('Ya tienes un servicio de grúa en curso y debes completarlo antes de aceptar uno nuevo.', 400);
        }

        // 2. Resolver el ID del servicio
        $serviceId = null;
        if (is_numeric($serviceIdentifier)) {
            $serviceId = (int)$serviceIdentifier;
        } else {
            $found = $this->towServiceRepository->findByUuid(Uuid::fromString((string)$serviceIdentifier));
            if ($found === null) {
                throw new Exception('El servicio de asistencia solicitado no existe.', 404);
            }
            $serviceId = $found->getId();
        }

        if ($serviceId === null) {
            throw new Exception('Identificador de servicio no válido.', 400);
        }

        // 3. Bloqueo pesimista para evitar condiciones de carrera (NFR-11-B)
        $service = $this->towServiceRepository->lockForUpdate($serviceId);
        if ($service === null) {
            throw new Exception('El servicio de asistencia solicitado no existe.', 404);
        }

        if ($service->getStatus() !== TowServiceStatus::Pending) {
            throw new DomainException('El servicio ya fue tomado por otro operador o no se encuentra disponible.', 409);
        }

        // 3.1. Validar pago de excedente o servicio SOS Express previo a la aceptación (Bypass de Pago Protection)
        if ($service->getExtraOrderId() !== null && $this->orderRepository !== null) {
            $order = $this->orderRepository->findById($service->getExtraOrderId());
            if ($order === null || !$order->isPaid()) {
                throw new DomainException('El servicio requiere la confirmación del pago del excedente antes de ser aceptado.', 402);
            }
        }

        // 4. Asignar chofer y cambiar estado a IN_ROUTE
        $service->accept($driverId);
        $this->towServiceRepository->save($service);

        $dto = TowServiceResponseDto::fromEntity($service)->toArray();

        // 5. Notificar al cliente por WebSocket seguro que la grúa va en camino (FR-24, FR-26)
        $this->broadcaster?->broadcast(
            channels: "private-tow-{$service->getUuid()->getValue()}",
            event: 'service-accepted',
            data: $dto
        );

        return $dto;
    }
}
