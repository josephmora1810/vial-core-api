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

        $emailHash = md5(strtolower($email->getValue()));
        $redisKey = 'password_reset:' . $emailHash;
        $attemptsKey = 'password_reset_attempts:' . $emailHash;

        $storedCode = $this->redis->execute('GET', $redisKey);

        if (!$storedCode) {
            throw new Exception("El código de recuperación es inválido o ha expirado.", 400);
        }

        if ($storedCode !== $code) {
            $attempts = (int)$this->redis->execute('INCR', $attemptsKey);
            $this->redis->execute('EXPIRE', $attemptsKey, 900);

            if ($attempts >= 5) {
                // Invalida inmediatamente el código para frustrar ataques de fuerza bruta
                $this->redis->execute('DEL', $redisKey);
                $this->redis->execute('DEL', $attemptsKey);
                throw new Exception("Ha superado el límite de intentos fallidos (5). El código ha sido invalidado por seguridad.", 429);
            }

            throw new Exception("El código de recuperación es inválido o ha expirado.", 400);
        }

        // Si el código coincide, limpiar contador de intentos
        $this->redis->execute('DEL', $attemptsKey);

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
