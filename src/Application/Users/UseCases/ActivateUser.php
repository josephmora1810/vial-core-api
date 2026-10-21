<?php

namespace App\Application\Users\UseCases;

use App\Domain\Users\Repositories\UserRepositoryInterface;
use App\Infrastructure\Logging\IntegrationLogger;
use Exception;

class ActivateUser
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private ?IntegrationLogger $logger = null
    ) {
        $this->logger ??= new IntegrationLogger();
    }

    /**
     * Reactiva una cuenta de usuario previamente suspendida o inactiva (FR-35).
     *
     * @param int $userId ID del usuario a reactivar
     * @param int|null $adminUserId ID del administrador que ejecuta la acción
     * @return array<string, mixed>
     */
    public function execute(int $userId, ?int $adminUserId = null): array
    {
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new Exception('Usuario no encontrado.', 404);
        }

        if ($user->isActive()) {
            throw new Exception('El usuario ya se encuentra activo.', 409);
        }

        $user->activate();
        $this->userRepository->save($user);

        $this->logger?->log(
            service: 'moderation',
            operation: 'activate_user',
            url: "/admin/users/{$userId}/activate",
            statusCode: 200,
            durationMs: 0.0,
            requestPayload: [
                'target_user_id' => $userId,
                'target_email'   => $user->getEmail()->getValue(),
                'admin_user_id'  => $adminUserId,
            ],
            responsePayload: 'USER_ACTIVATED'
        );

        return [
            'activated'  => true,
            'user_id'    => $userId,
            'uuid'       => $user->getUuid()->getValue(),
            'email'      => $user->getEmail()->getValue(),
            'full_name'  => $user->getFullName(),
            'is_active'  => true,
            'message'    => 'Usuario reactivado exitosamente.'
        ];
    }
}
