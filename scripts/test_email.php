<?php

use App\Core\DotEnv;
use App\Infrastructure\Mail\SmtpMailer;

$rootPath = dirname(__DIR__);
require_once "{$rootPath}/autoload.php";

$envFile = $rootPath . "/.env";
if (file_exists($envFile)) {
    (new DotEnv($envFile))->load();
}

$recipient = $argv[1] ?? null;

if (!$recipient) {
    echo "\nUso: php scripts/test_email.php tu_correo@gmail.com\n\n";
    exit(1);
}

echo "Iniciando prueba SMTP hacia {$recipient}...\n";
echo "Host: " . ($_ENV['SMTP_HOST'] ?? '') . ":" . ($_ENV['SMTP_PORT'] ?? '') . "\n";
echo "Usuario: " . ($_ENV['SMTP_USERNAME'] ?? '') . "\n";
echo "Encriptación: " . ($_ENV['SMTP_ENCRYPTION'] ?? '') . "\n\n";

$mailer = new SmtpMailer(
    host: $_ENV['SMTP_HOST'] ?? 'localhost',
    port: (int)($_ENV['SMTP_PORT'] ?? 465),
    username: $_ENV['SMTP_USERNAME'] ?? '',
    password: $_ENV['SMTP_PASSWORD'] ?? '',
    fromAddress: $_ENV['MAIL_FROM_ADDRESS'] ?? 'info@artgoritmo.net',
    fromName: $_ENV['MAIL_FROM_NAME'] ?? 'UbikGo Asistencia Vial',
    encryption: $_ENV['SMTP_ENCRYPTION'] ?? 'ssl',
    timeout: 15
);

$subject = "Prueba de Servidor SMTP - UbikGo";
$html = "
    <div style='font-family: sans-serif; max-width: 500px; margin: auto; padding: 20px; border: 1px solid #eee; border-radius: 8px;'>
        <h2 style='color: #0d6efd;'>UbikGo Core API</h2>
        <p>¡Hola! Este es un correo de prueba enviado desde tu servidor SMTP de <strong>artgoritmo.net</strong>.</p>
        <p>Si estás leyendo esto, significa que:</p>
        <ul>
            <li>La conexión por sockets SSL/TLS funciona correctamente.</li>
            <li>La autenticación con tus credenciales cPanel fue exitosa.</li>
            <li>El sistema de recuperación de contraseñas de UbikGo está listo para enviar códigos OTP.</li>
        </ul>
        <hr style='border: none; border-top: 1px solid #eee;' />
        <small style='color: #888;'>Fecha: " . date('Y-m-d H:i:s') . "</small>
    </div>
";

$success = $mailer->send($recipient, $subject, $html);

if ($success) {
    echo "¡ÉXITO! Correo enviado correctamente a {$recipient}. Revisa tu bandeja de entrada o spam.\n";
} else {
    echo "FALLO: No se pudo enviar el correo. Revisa tus credenciales o los datos en .env.\n";
}
