<?php

namespace App\Infrastructure\Mail;

use App\Domain\Services\MailerInterface;
use Exception;

class SmtpMailer implements MailerInterface
{
    public function __construct(
        private string $host,
        private int $port,
        private string $username,
        private string $password,
        private string $fromAddress,
        private string $fromName,
        private string $encryption = 'ssl', // o 'tls'
        private int $timeout = 30
    ) {}

    public function send(string $to, string $subject, string $htmlBody): bool
    {
        // Configuración de la conexión SMTP
        $protocol = strtolower($this->encryption) === 'ssl' ? 'ssl://' : '';
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
                'allow_self_signed'=> true,
            ],
        ]);

        $socket = @stream_socket_client(
            $protocol . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            error_log("Fallo al conectar con SMTP: $errstr ($errno)");
            return false;
        }

        try {
            $this->expect($socket, 220);
            $this->command($socket, "EHLO " . gethostname(), 250);
            if (strtolower($this->encryption) === 'tls') {
                $this->command($socket, "STARTTLS", 220);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->command($socket, "EHLO " . gethostname(), 250);
            }
            // Autenticación
            $this->command($socket, "AUTH LOGIN", 334);
            $this->command($socket, base64_encode($this->username), 334);
            $this->command($socket, base64_encode($this->password), 235);
            // Remitente y Destinatario
            $this->command($socket, "MAIL FROM: <{$this->fromAddress}>", 250);
            $this->command($socket, "RCPT TO: <{$to}>", 250);
            // Cabeceras y Cuerpo
            $this->command($socket, "DATA", 354);
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: =?UTF-8?B?" . base64_encode($this->fromName) . "?= <{$this->fromAddress}>\r\n";
            $headers .= "To: <{$to}>\r\n";
            $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $message = $headers . "\r\n" . $htmlBody . "\r\n.\r\n";
            fwrite($socket, $message);
            $this->expect($socket, 250);
            $this->command($socket, "QUIT", 221);
            fclose($socket);
            return true;
        } catch (Exception $e) {
            error_log("Error enviando email SMTP: " . $e->getMessage());
            @fclose($socket);
            return false;
        }
    }

    private function command($socket, string $cmd, int $expectedCode): string
    {
        fwrite($socket, $cmd . "\r\n");
        return $this->expect($socket, $expectedCode);
    }
    private function expect($socket, int $expectedCode): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new Exception("Esperado código $expectedCode pero recibido $code: $response");
        }
        return $response;
    }
}