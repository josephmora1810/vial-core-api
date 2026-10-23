<?php

namespace App\Application\Towing\UseCases;

use App\Application\Towing\DTOs\TowServiceResponseDto;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Towing\Entities\TowService;
use App\Domain\Towing\Repositories\TowServiceRepositoryInterface;
use DomainException;
use Exception;
use LogicException;

class ArriveTowService
{
    public function __construct(
        private TowServiceRepositoryInterface $towServiceRepository
    ) {}

    /**
     * Marca la llegada de la grúa al lugar donde se encuentra el cliente varado (IN_ROUTE -> IN_PROGRESS).
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
            $service->arrive();
        } catch (LogicException $e) {
            throw new DomainException($e->getMessage(), 400);
        }

        $this->towServiceRepository->save($service);

        return TowServiceResponseDto::fromEntity($service)->toArray();
    }
}
