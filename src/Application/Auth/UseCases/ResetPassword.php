<?php

namespace App\Application\Auth\UseCases;

use App\Domain\Auth\Repositories\PersonalAccessTokenRepositoryInterface;
use App\Domain\Users\Repositories\UserRepositoryInterface;
use App\Domain\Users\ValueObjects\Email;
use App\Domain\Auth\ValueObjects\Password;
use App\Infrastructure\Database\Redis;
use Exception;
use InvalidArgumentException;

class ResetPassword
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PersonalAccessTokenRepositoryInterface $tokenRepository,
        private Redis $redis
    ) {}

    public function execute(
        string $rawEmail,
        string $rawCode,
        string $newPassword,
        string $newPasswordConfirmation
    ): void {
        if ($newPassword !== $newPasswordConfirmation) {
            throw new Exception("Las contraseñas no coinciden.", 400);
        }

        try {
            $email = new Email($rawEmail);
        } catch (InvalidArgumentException) {
            throw new Exception("El formato del correo electrónico no es válido.", 400);
        }

        $code = trim($rawCode);
        if (empty($code)) {
            throw new Exception("El código de verificación es requerido.", 400);
        }

        $redisKey = 'password_reset:' . md5(strtolower($email->getValue()));
        $storedCode = $this->redis->execute('GET', $redisKey);

        if (!$storedCode || $storedCode !== $code) {
            throw new Exception("El código de recuperación es inválido o ha expirado.", 400);
        }

        $user = $this->userRepository->findByEmail($email);
        if (!$user) {
            throw new Exception("Usuario no encontrado.", 404);
        }

        // Valida política de contraseña y cifra en Bcrypt
        $passwordVo = Password::fromPlainText($newPassword);
        $user->setPassword($passwordVo);

        // Guardamos en MariaDB
        $this->userRepository->save($user);

        // Revocamos todas las sesiones activas en caso de hackeo o robo de dispositivo
        $this->tokenRepository->revokeAllForUserId((int)$user->getId());

        // Consumir el código para que no sea reutilizable
        $this->redis->execute('DEL', $redisKey);
    }
}