<?php

namespace App\Domain\Towing\Repositories;

use App\Domain\Towing\Entities\Rating;

interface RatingRepositoryInterface
{
    public function save(Rating $rating): void;

    /** Verifica si el usuario ya calificó este servicio (evita doble calificación) */
    public function findByServiceAndUser(int $towServiceId, int $userId): ?Rating;

    /** Retorna todas las calificaciones de un usuario (para chofer o cliente) */
    public function findByRatedUserId(int $ratedUserId): array;

    /** Calcula el promedio de estrellas de un usuario (ej. 4.85) */
    public function getAverageRatingForUser(int $ratedUserId): float;
}