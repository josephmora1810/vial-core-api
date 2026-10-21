<?php

namespace App\Controllers;

use App\Application\Users\UseCases\ActivateUser;
use App\Application\Users\UseCases\SuspendUser;
use App\Application\Users\UseCases\UploadUserAvatar;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Users\Entities\User;
use Exception;

class UserModerationController
{
    public function __construct(
        private SuspendUser $suspendUser,
        private ActivateUser $activateUser,
        private ?UploadUserAvatar $uploadUserAvatar = null
    ) {}

    /**
     * POST /api/v1/admin/users/{id}/suspend
     * Inhabilita/suspende un usuario (FR-35).
     */
    public function suspend(Request $request, string|int $id): void
    {
        /** @var User|null $admin */
        $admin = $request->user();
        $targetUserId = (int)$id;

        $reason = $request->input('reason');
        if (empty($reason)) {
            Response::json(['reason' => 'Debe suministrar un motivo de suspensión.'], 422, 'fail', 'Validación fallida.');
            return;
        }

        try {
            $result = $this->suspendUser->execute(
                userId: $targetUserId,
                reason: (string)$reason,
                adminUserId: $admin?->getId()
            );

            Response::json($result, 200, 'success', 'Usuario suspendido exitosamente.');
        } catch (Exception $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 400;
            Response::json([], $httpCode, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/admin/users/{id}/activate
     * Reactiva un usuario previamente suspendido (FR-35).
     */
    public function activate(Request $request, string|int $id): void
    {
        /** @var User|null $admin */
        $admin = $request->user();
        $targetUserId = (int)$id;

        try {
            $result = $this->activateUser->execute(
                userId: $targetUserId,
                adminUserId: $admin?->getId()
            );

            Response::json($result, 200, 'success', 'Usuario reactivado exitosamente.');
        } catch (Exception $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 400;
            Response::json([], $httpCode, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/users/avatar
     * Carga y actualiza la foto de perfil del usuario autenticado en S3 y la tabla polimórfica images.
     */
    public function uploadAvatar(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        if ($this->uploadUserAvatar === null) {
            Response::json([], 500, 'error', 'Servicio de avatares no configurado.');
            return;
        }

        $avatarPath = null;
        if (!empty($_FILES['avatar']['tmp_name'])) {
            $uploaded = $_FILES['avatar'];
            $ext = pathinfo($uploaded['name'], PATHINFO_EXTENSION);
            $uploadsDir = dirname(__DIR__, 2) . '/storage/documents';
            $targetPath = $uploadsDir . '/avatar_' . $user->getId() . '_' . bin2hex(random_bytes(6)) . '.' . ($ext ?: 'jpg');
            if (move_uploaded_file($uploaded['tmp_name'], $targetPath)) {
                $avatarPath = $targetPath;
            }
        } elseif (!empty($request->input('avatar_path'))) {
            $avatarPath = (string)$request->input('avatar_path');
        }

        if (empty($avatarPath)) {
            Response::json(['avatar' => 'Debe adjuntar una imagen para la foto de perfil.'], 422, 'fail', 'Archivo requerido.');
            return;
        }

        try {
            $result = $this->uploadUserAvatar->execute(
                userId: $user->getId(),
                localFilePath: $avatarPath
            );

            Response::json($result, 200, 'success', 'Foto de perfil actualizada exitosamente.');
        } catch (Exception $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 400;
            Response::json([], $httpCode, 'fail', $e->getMessage());
        }
    }
}
