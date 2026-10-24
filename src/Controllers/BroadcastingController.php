<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Application\Towing\DTOs\PusherAuthResponseDto;
use App\Core\{Request, Response};
use App\Domain\Shared\Services\BroadcasterInterface;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Towing\Repositories\TowServiceRepositoryInterface;
use Exception;
use InvalidArgumentException;

class BroadcastingController
{
    public function __construct(
        private BroadcasterInterface $broadcaster,
        private TowServiceRepositoryInterface $towServiceRepository
    ) {}

    /**
     * POST /api/v1/broadcasting/auth
     * Autenticación oficial de canales privados y de presencia de Pusher (OWASP Security / WebSockets).
     */
    public function auth(Request $request): void
    {
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autenticado.');
            return;
        }

        $channelName = trim((string)$request->input('channel_name'));
        $socketId = trim((string)$request->input('socket_id'));

        if (empty($channelName) || empty($socketId)) {
            Response::json([], 422, 'fail', 'channel_name y socket_id son obligatorios.');
            return;
        }

        $userId = (int)$user->getId();
        $userRole = (string)$user->getRole();
        $isAdmin = ($userRole === 'admin');

        // 1. Canal de Radar de Grúas: Solo operadores de grúa (driver) o administradores
        if ($channelName === 'private-cranes-radar') {
            if ($userRole !== 'driver' && !$isAdmin) {
                Response::json([], 403, 'fail', 'No tienes autorización para acceder al radar de grúas.');
                return;
            }
        }
        // 2. Canal privado de asistencia SOS (telemetría y estado): Solo el cliente, el chofer asignado o admin
        elseif (str_starts_with($channelName, 'private-tow-')) {
            $uuidStr = substr($channelName, strlen('private-tow-'));
            try {
                $uuid = Uuid::fromString($uuidStr);
                $service = $this->towServiceRepository->findByUuid($uuid);
            } catch (InvalidArgumentException) {
                Response::json([], 400, 'fail', 'Identificador de servicio en canal no válido.');
                return;
            }

            if ($service === null) {
                Response::json([], 404, 'fail', 'El servicio de asistencia asociado no existe.');
                return;
            }

            $isClient = ($service->getClientId() === $userId);
            $isDriver = ($service->getDriverId() === $userId);

            if (!$isClient && !$isDriver && !$isAdmin) {
                Response::json([], 403, 'fail', 'No tienes autorización para suscribirte a este servicio de auxilio.');
                return;
            }
        }
        // 3. Canal privado de notificaciones de usuario
        elseif (str_starts_with($channelName, 'private-user-')) {
            $targetUserId = (int)substr($channelName, strlen('private-user-'));
            if ($targetUserId !== $userId && !$isAdmin) {
                Response::json([], 403, 'fail', 'No tienes autorización para acceder al canal de otro usuario.');
                return;
            }
        }

        // Datos para canales de presencia si aplica
        $channelData = null;
        if (str_starts_with($channelName, 'presence-')) {
            $channelData = [
                'user_id'   => $userId,
                'user_info' => [
                    'name' => $user->getFullName(),
                    'role' => $userRole,
                ],
            ];
        }

        try {
            $authData = $this->broadcaster->authorizeChannel($channelName, $socketId, $channelData);
            $dto = PusherAuthResponseDto::fromArray($authData);
            Response::rawJson($dto->toArray(), 200);
        } catch (Exception $e) {
            Response::json([], 500, 'error', 'Error al autorizar canal: ' . $e->getMessage());
        }
    }
}
