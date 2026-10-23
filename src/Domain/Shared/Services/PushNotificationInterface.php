<?php

namespace App\Domain\Shared\Services;

interface PushNotificationInterface
{
    /**
     * Envía una notificación push nativa hacia uno o varios canales de interés (ej: 'cranes-radar', 'driver-updates').
     *
     * @param array<string> $interests Lista de canales de interés
     * @param string $title Título de la notificación
     * @param string $body Mensaje descriptivo
     * @param array<string, mixed> $data Carga útil (payload) adicional para abrir pantallas en la app móvil
     * @return bool
     */
    public function publishToInterests(array $interests, string $title, string $body, array $data = []): bool;

    /**
     * Envía una notificación push nativa dirigida a usuarios específicos por su identificador público (UUID).
     *
     * @param array<string> $userIds Lista de UUIDs de usuarios
     * @param string $title
     * @param string $body
     * @param array<string, mixed> $data
     * @return bool
     */
    public function publishToUsers(array $userIds, string $title, string $body, array $data = []): bool;
}
