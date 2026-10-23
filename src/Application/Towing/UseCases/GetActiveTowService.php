<?php

namespace App\Application\Towing\UseCases;

use App\Domain\Towing\Repositories\TowServiceRepositoryInterface;

class GetActiveTowService
{
    public function __construct(
        private TowServiceRepositoryInterface $towServiceRepository,
        private GetTowService $getTowService
    ) {}

    /**
     * Retorna el servicio de auxilio vial activo para el usuario autenticado (Cliente o Conductor).
     * Devuelve null si el usuario no tiene ningún auxilio en PENDING, EN_ROUTE o IN_PROGRESS.
     *
     * @param int $userId ID del usuario autenticado
     * @param string $role Rol principal del usuario ('client' o 'driver')
     * @return array<string, mixed>|null
     */
    public function execute(int $userId, string $role = 'client'): ?array
    {
        $service = ($role === 'driver')
            ? $this->towServiceRepository->findActiveByDriverId($userId)
            : $this->towServiceRepository->findActiveByClientId($userId);

        // Fallback cruzado: si no se encontró con el rol principal, verificar si actúa en el otro rol
        if ($service === null) {
            $service = ($role === 'driver')
                ? $this->towServiceRepository->findActiveByClientId($userId)
                : $this->towServiceRepository->findActiveByDriverId($userId);
        }

        if ($service === null) {
            return null;
        }

        return $this->getTowService->execute(
            serviceIdentifier: $service->getUuid()->getValue(),
            requestingUserId: $userId,
            isAdmin: false
        );
    }
}
