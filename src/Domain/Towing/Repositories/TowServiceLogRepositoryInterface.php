<?php

namespace App\Domain\Towing\Repositories;

use App\Domain\Towing\Entities\TowServiceLog;

interface TowServiceLogRepositoryInterface
{
    /** Guarda un nuevo pulso GPS en tow_service_logs (append-only) */
    public function append(TowServiceLog $log): void;

    /** Retorna la última ubicación registrada de la grúa para un servicio */
    public function getLatestByServiceId(int $towServiceId): ?TowServiceLog;

    /** 
     * Retorna la traza completa de puntos GPS para dibujar la ruta recorrida en el mapa 
     * @return list<TowServiceLog>
     */
    public function getHistoryByServiceId(int $towServiceId): array;
}