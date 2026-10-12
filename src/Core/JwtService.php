<?php

namespace App\Core;

use Exception;

/**
 * Servicio Criptográfico de JSON Web Tokens (JWT) en PHP Puro.
 * Implementa el estándar RFC 7519 con firma HMAC SHA-256 (HS256).
 * No requiere librerías externas de Composer.
 */
class JwtService{
    private string $secretKey;
    private int $ttlSeconds;

    public function __construct() {
        // Obtenemos la clave secreta del .env o usamos una por defecto en local
        $this->secretKey = $_ENV['JWT_SECRET'] ?? 'ubikgo_super_secret_jwt_key_2026';
        // Duración estándar del token de acceso: 1 hora (3600s)
        $this->ttlSeconds = (int)($_ENV['JWT_TTL'] ?? 3600);
    }

    /**
     * Genera un token JWT firmado.
     */
    public function generateToken(array $payload): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];
        // Tiempos de emisión y expiración
        $issuedAt = time();
        $payload['iat'] = $issuedAt;
        $payload['exp'] = $issuedAt + $this->ttlSeconds;
        $base64Header = $this->base64UrlEncode(json_encode($header));
        $base64Payload = $this->base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", $this->secretKey, true);
        $base64Signature = $this->base64UrlEncode($signature);
        return "{$base64Header}.{$base64Payload}.{$base64Signature}";
    }

    /**
     * Valida la firma y vigencia de un token y devuelve el payload descifrado.
     */
    public function validateToken(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new Exception("Formato de token inválido.", 401);
        }
        list($base64Header, $base64Payload, $base64Signature) = $parts;
        // Verificar la firma criptográfica
        $expectedSignature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", $this->secretKey, true);
        $expectedBase64Signature = $this->base64UrlEncode($expectedSignature);
        if (!hash_equals($expectedBase64Signature, $base64Signature)) {
            throw new Exception("Firma de token inválida. Acceso no autorizado.", 401);
        }
        $payload = json_decode($this->base64UrlDecode($base64Payload), true);
        // Verificar expiración (TTL)
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new Exception("El token ha expirado. Por favor inicia sesión nuevamente.", 401);
        }
        return $payload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}