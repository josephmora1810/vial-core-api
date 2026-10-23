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

    /**
     * Genera la firma criptográfica HMAC-SHA256 para autorizar la suscripción de un cliente
     * a canales privados ('private-*') o de presencia ('presence-*') según el protocolo oficial de Pusher.
     *
     * @param string $channelName Nombre del canal (ej: 'private-cranes-radar', 'private-tow-uuid')
     * @param string $socketId Identificador de socket asignado por el servidor de WebSockets al cliente
     * @param array<string, mixed>|null $channelData Datos del usuario si el canal es de presencia ('presence-*')
     * @return array<string, string> Payload con la firma de autorización {'auth': 'app_key:signature'}
     */
    public function authorizeChannel(string $channelName, string $socketId, ?array $channelData = null): array;
}
