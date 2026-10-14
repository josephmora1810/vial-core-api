<?php

namespace App\Application\UseCases;

use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Services\MailerInterface;
use App\Domain\ValueObjects\Email;
use App\Infrastructure\Database\Redis;
use Exception;
use InvalidArgumentException;

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
        $redisKey = 'password_reset:' . md5(strtolower($email->getValue()));

        // Guardar en Redis con expiración de 15 minutos (900 seg)
        $this->redis->execute('SET', $redisKey, $otp);
        $this->redis->execute('EXPIRE', $redisKey, 900);

        // Contenido del Correo
        $subject = "UbikGo - Código de Recuperación de Contraseña";
        $html = "
            <div style='font-family: sans-serif; max-width: 500px; margin: auto; padding: 20px; border: 1px solid #eee; border-radius: 8px;'>
                <h2 style='color: #0d6efd;'>UbikGo Asistencia Vial</h2>
                <p>Hola, <strong>{$user->getFullName()}</strong>:</p>
                <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta.</p>
                <div style='background: #f8f9fa; text-align: center; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                    <span style='font-size: 32px; font-weight: bold; letter-spacing: 6px; color: #333;'>{$otp}</span>
                </div>
                <p style='color: #666; font-size: 14px;'>Este código expirará en 15 minutos. Si no realizaste esta solicitud, puedes ignorar este correo de forma segura.</p>
            </div>
        ";

        $this->mailer->send($email->getValue(), $subject, $html);
    }
}