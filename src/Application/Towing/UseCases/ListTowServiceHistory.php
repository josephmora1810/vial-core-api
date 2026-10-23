<?php

namespace App\Application\Towing\UseCases;

use App\Application\Towing\DTOs\TowServiceResponseDto;
use App\Domain\Towing\Repositories\TowServiceRepositoryInterface;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;

class ListTowServiceHistory
{
    public function __construct(
        private TowServiceRepositoryInterface $towServiceRepository,
        private ?VehicleRepositoryInterface $vehicleRepository = null
    ) {}

    /**
     * Retorna el historial paginado de servicios de asistencia vial para el usuario autenticado.
     *
     * @param int $userId ID del usuario
     * @param string $role 'client' o 'driver'
     * @param int $page Número de página (1-based)
     * @param int $limit Límite de elementos por página (default: 10, max: 100)
     * @param string|null $status Filtro opcional por estatus (ej: 'COMPLETED', 'CANCELLED_BY_CLIENT')
     * @return array{items: list<array<string, mixed>>, pagination: array{total: int, page: int, limit: int, total_pages: int}}
     */
    public function execute(
        int $userId,
        string $role = 'client',
        int $page = 1,
        int $limit = 10,
        ?string $status = null
    ): array {
        $result = $this->towServiceRepository->findHistoryByUserId(
            userId: $userId,
            role: $role,
            page: $page,
            limit: $limit,
            status: $status
        );

        $dtos = [];
        foreach ($result['items'] as $service) {
            $plate = null;
            if ($this->vehicleRepository !== null) {
                $vehicle = $this->vehicleRepository->findById($service->getVehicleId());
                $plate = $vehicle?->getPlateNumber()?->getValue();
            }
            $dtos[] = TowServiceResponseDto::fromEntity($service, $plate)->toArray();
        }

        return [
            'items'      => $dtos,
            'pagination' => [
                'total'       => $result['total'],
                'page'        => $result['page'],
                'limit'       => $result['limit'],
                'total_pages' => $result['total_pages'],
            ],
        ];
    }
}
