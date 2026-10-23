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

        // CWE-73 / CWE-59: Rechazar rutas manuales de archivo. Solo aceptar subidas vía $_FILES
        if (empty($_FILES['avatar']['tmp_name'])) {
            Response::json(['avatar' => 'Debe adjuntar una imagen válida mediante subida de archivo (multipart/form-data).'], 422, 'fail', 'Archivo requerido.');
            return;
        }

        $uploaded = $_FILES['avatar'];
        $ext = strtolower(pathinfo($uploaded['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowedExtensions, true)) {
            Response::json(['avatar' => 'Extensión de archivo no permitida. Formatos aceptados: JPG, PNG, WEBP.'], 422, 'fail', 'Formato no permitido.');
            return;
        }

        // CWE-434: Validación estricta del tipo MIME real del binario
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $uploaded['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowedMimes, true)) {
            Response::json(['avatar' => 'El archivo adjunto no es una imagen válida.'], 422, 'fail', 'MIME type inválido.');
            return;
        }

        $uploadsDir = dirname(__DIR__, 2) . '/storage/documents';
        if (!is_dir($uploadsDir)) {
            @mkdir($uploadsDir, 0755, true);
        }
        $targetPath = $uploadsDir . '/avatar_' . $user->getId() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

        $moved = is_uploaded_file($uploaded['tmp_name'])
            ? move_uploaded_file($uploaded['tmp_name'], $targetPath)
            : copy($uploaded['tmp_name'], $targetPath);

        if (!$moved) {
            Response::json([], 500, 'error', 'No se pudo guardar la imagen temporal en el servidor.');
            return;
        }

        $avatarPath = $targetPath;

        try {
            $result = $this->uploadUserAvatar->execute(
                userId: $user->getId(),
                localFilePath: $avatarPath
            );
            $result['initials'] = $user->getInitials();

            Response::json($result, 200, 'success', 'Foto de perfil actualizada exitosamente.');
        } catch (Exception $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 400;
            Response::json([], $httpCode, 'fail', $e->getMessage());
        }
    }
}
