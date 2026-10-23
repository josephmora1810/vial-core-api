<?php

namespace App\Domain\Towing\Repositories;

use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Towing\Entities\TowService;

interface TowServiceRepositoryInterface
{
    public function save(TowService $service): void;

    public function findById(int $id): ?TowService;

    public function findByUuid(Uuid $uuid): ?TowService;

    /** Retorna el servicio activo de un cliente (PENDING, IN_ROUTE, IN_PROGRESS) */
    public function findActiveByClientId(int $clientId): ?TowService;

    /** Retorna el servicio que un conductor tiene asignado actualmente */
    public function findActiveByDriverId(int $driverId): ?TowService;

    /**
     * CRUCIAL PARA CONCURRENCIA (FR-23):
     * Bloquea pesimistamente la fila en MariaDB (SELECT ... FOR UPDATE)
     * para que dos grúas no puedan aceptar el mismo servicio simultáneamente.
     */
    public function lockForUpdate(int $id): ?TowService;

    /**
     * Busca solicitudes PENDING dentro de un rango geográfico para el radar de grúas
     * @return list<TowService>
     */
    public function findPendingNearby(Coordinates $center, float $radiusKm = 25.0): array;

    /**
     * Retorna solicitudes en PENDING que superaron el tiempo límite de espera sin asignación (FR-24-C).
     * @return list<TowService>
     */
    public function findOverduePendingServices(int $timeoutMinutes = 10): array;
}
