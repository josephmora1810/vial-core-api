<?php

namespace App\Domain\Shared\Services;

interface BroadcasterInterface
{
    /**
     * Emite un evento en tiempo real hacia uno o varios canales de WebSockets (FR-23, FR-25, FR-26).
     *
     * @param string|array<string> $channels Canal o lista de canales
     * @param string $event Nombre del evento (ej: 'new-sos-request', 'location-updated')
     * @param array<string, mixed> $data Carga útil (payload) del evento
     * @return bool Retorna true si fue transmitido exitosamente
     */
    public function broadcast(string|array $channels, string $event, array $data): bool;
}
