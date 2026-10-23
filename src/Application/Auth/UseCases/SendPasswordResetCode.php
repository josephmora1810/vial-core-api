<?php

namespace App\Application\Auth\UseCases;

use App\Domain\Users\Repositories\UserRepositoryInterface;
use App\Domain\Shared\Services\MailerInterface;
use App\Domain\Users\ValueObjects\Email;
use App\Infrastructure\Database\Redis;
use App\Infrastructure\Mail\UbikEmailTemplates;
use Exception;
use InvalidArgumentException;
use Throwable;

class SendPasswordResetCode
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private MailerInterface $mailer,
        private Redis $redis
    ) {}

    public function execute(string $rawEmail): void
    {
        try {
            $email = new Email($rawEmail);
        } catch (InvalidArgumentException) {
            throw new Exception("El formato del correo electrónico no es válido.", 400);
        }

        $user = $this->userRepository->findByEmail($email);

        // Buenas prácticas OWASP: si el usuario no existe, salimos silenciosamente
        if (!$user) {
            return;
        }

        // Generar OTP de 6 dígitos criptográficamente seguro
        $otp = sprintf('%06d', random_int(0, 999999));
        $emailHash = md5(strtolower($email->getValue()));
        $redisKey = 'password_reset:' . $emailHash;
        $attemptsKey = 'password_reset_attempts:' . $emailHash;

        // Guardar en Redis con expiración de 15 minutos (900 seg) y reiniciar intentos
        $this->redis->execute('SET', $redisKey, $otp);
        $this->redis->execute('EXPIRE', $redisKey, 900);
        $this->redis->execute('DEL', $attemptsKey);

        // Contenido del Correo con identidad visual corporativa UbikGo
        $subject = "UbikGo - Código de Recuperación de Contraseña";
        $html = UbikEmailTemplates::passwordResetOtp($user->getFullName(), $otp);

        try {
            $this->mailer->send($email->getValue(), $subject, $html);
        } catch (Throwable $e) {
            error_log("Error silencioso enviando correo OTP: " . $e->getMessage());
        }
    }
}
