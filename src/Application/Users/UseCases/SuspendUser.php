<?php

namespace App\Application\Users\UseCases;

use App\Domain\Auth\Repositories\PersonalAccessTokenRepositoryInterface;
use App\Domain\Users\Repositories\UserRepositoryInterface;
use App\Infrastructure\Logging\IntegrationLogger;
use Exception;

class SuspendUser
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private ?PersonalAccessTokenRepositoryInterface $tokenRepository = null,
        private ?IntegrationLogger $logger = null
    ) {
        $this->logger ??= new IntegrationLogger();
    }

    /**
     * Inhabilita/suspende un usuario del sistema (FR-35).
     * Revoca inmediatamente sus Refresh Tokens para expulsarlo de sesiones activas.
     *
     * @param int $userId ID del usuario a suspender
     * @param string $reason Motivo de auditoría administrativa
     * @param int|null $adminUserId ID del administrador que ejecuta la acción
     * @return array<string, mixed>
     */
    public function execute(int $userId, string $reason, ?int $adminUserId = null): array
    {
        if (trim($reason) === '') {
            throw new Exception('Debe suministrar un motivo de suspensión o baneo para auditoría.', 422);
        }

        if ($adminUserId !== null && $adminUserId === $userId) {
            throw new Exception('Un administrador no puede suspender su propia cuenta.', 400);
        }

        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new Exception('Usuario no encontrado.', 404);
        }

        if (!$user->isActive()) {
            throw new Exception('El usuario ya se encuentra suspendido/inactivo.', 409);
        }

        // 1. Desactivar usuario
        $user->deactivate();
        $this->userRepository->save($user);

        // 2. Revocar todos sus Refresh Tokens activos
        $this->tokenRepository?->revokeAllForUserId($userId);

        // 3. Registrar en logs de auditoría de seguridad
        $this->logger?->log(
            service: 'moderation',
            operation: 'suspend_user',
            url: "/admin/users/{$userId}/suspend",
            statusCode: 200,
            durationMs: 0.0,
            requestPayload: [
                'target_user_id' => $userId,
                'target_email'   => $user->getEmail()->getValue(),
                'admin_user_id'  => $adminUserId,
                'reason'         => trim($reason),
            ],
            responsePayload: 'USER_SUSPENDED'
        );

        return [
            'suspended'  => true,
            'user_id'    => $userId,
            'uuid'       => $user->getUuid()->getValue(),
            'email'      => $user->getEmail()->getValue(),
            'full_name'  => $user->getFullName(),
            'is_active'  => false,
            'reason'     => trim($reason),
            'message'    => 'Usuario suspendido exitosamente y sesiones activas revocadas.'
        ];
    }
}
