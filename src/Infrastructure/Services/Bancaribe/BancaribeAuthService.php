<?php

namespace App\Infrastructure\Services\Bancaribe;

use App\Domain\Payments\Repositories\BankTokenRepositoryInterface;
use App\Domain\Payments\Services\BankAuthServiceInterface;
use App\Domain\Shared\Services\RedisClientInterface;
use App\Infrastructure\Http\{HttpClientException, HttpClientInterface};
use DateTimeImmutable;
use RuntimeException;
use Throwable;

class BancaribeAuthService implements BankAuthServiceInterface
{
    private const CACHE_KEY = 'bancaribe_access_token';

    public function __construct(
        private HttpClientInterface $httpClient,
        private BankTokenRepositoryInterface $tokenRepository,
        private ?RedisClientInterface $redis = null,
        private string $baseAuthUrl = '',
        private string $oauth2Endpoint = 'oauth2/token',
        private string $customerKey = '',
        private string $customerSecret = '',
        private bool $sandboxMode = false,
        private bool $isLocalEnv = true
    ) {
        if ($this->sandboxMode && !$this->isLocalEnv) {
            throw new \LogicException('El modo sandbox de autenticación Bancaribe no está permitido fuera del entorno local.');
        }
    }

    public function getAccessToken(bool $forceRefresh = false): string
    {
        if (!$forceRefresh) {
            // 1. Consultar tabla bank_tokens en MariaDB primero (fuente de verdad persistente)
            try {
                $record = $this->tokenRepository->findByGateway('bancaribe');
                $now = new DateTimeImmutable();

                if ($record !== null && !empty($record['access_token']) && $record['expires_at'] !== null && $record['expires_at'] > $now) {
                    $remainingSeconds = $record['expires_at']->getTimestamp() - $now->getTimestamp();
                    if ($remainingSeconds > 60) {
                        $this->cacheToken($record['access_token'], max(60, $remainingSeconds - 60));
                        return $record['access_token'];
                    }
                }
            } catch (Throwable) {
                // Continuar a caché o nueva solicitud si falla la consulta en BD
            }

            // 2. Consultar caché en Redis como respaldo
            if ($this->redis !== null) {
                try {
                    $cachedToken = $this->redis->execute('GET', self::CACHE_KEY);
                    if (is_string($cachedToken) && $cachedToken !== '') {
                        return $cachedToken;
                    }
                } catch (Throwable) {
                    // Continuar si Redis falla
                }
            }
        }

        // 3. Solicitar nuevo token OAuth2 al banco
        return $this->requestNewAccessToken();
    }

    public function requestNewAccessToken(): string
    {
        if ($this->sandboxMode) {
            $fakeToken = 'sandbox_bancaribe_token_' . bin2hex(random_bytes(8));
            $expiresAt = (new DateTimeImmutable())->modify('+3600 seconds');
            try {
                $this->tokenRepository->upsert('bancaribe', $fakeToken, 'Bearer', $expiresAt);
            } catch (Throwable) {
            }
            $this->cacheToken($fakeToken, 3540);
            return $fakeToken;
        }

        $key = trim($this->customerKey);
        $secret = trim($this->customerSecret);

        if ($key === '' || $secret === '') {
            throw new RuntimeException('Credenciales de Bancaribe no configuradas (Customer Key / Secret vacíos).', 502);
        }

        $url = rtrim($this->baseAuthUrl, '/') . '/' . ltrim($this->oauth2Endpoint, '/');
        $basicAuth = base64_encode("{$key}:{$secret}");

        try {
            // Intento 1: Estándar OAuth2 RFC 6749 con Basic Auth y x-www-form-urlencoded
            $response = $this->httpClient->postForm(
                url: $url,
                data: ['grant_type' => 'client_credentials'],
                headers: [
                    'Authorization' => "Basic {$basicAuth}",
                    'Accept'        => 'application/json',
                ]
            );

            // Intento 2: Si el reverse proxy bancario filtra la cabecera Authorization, enviar en el body
            if (!$response->isSuccessful() && $response->status() === 401) {
                $response = $this->httpClient->postForm(
                    url: $url,
                    data: [
                        'grant_type'    => 'client_credentials',
                        'client_id'     => $key,
                        'client_secret' => $secret,
                    ],
                    headers: [
                        'Accept' => 'application/json',
                    ]
                );
            }
        } catch (Throwable $e) {
            throw new RuntimeException('Error de red al obtener el token de acceso de Bancaribe: ' . $e->getMessage(), 502, $e);
        }

        if (!$response->isSuccessful()) {
            throw new RuntimeException('Error al obtener el token de acceso de Bancaribe: ' . $response->body(), 502);
        }

        try {
            $data = $response->json();
        } catch (HttpClientException $e) {
            throw new RuntimeException('Respuesta OAuth2 de Bancaribe inválida: no es un JSON válido.', 502, $e);
        }
        if (empty($data['access_token'])) {
            throw new RuntimeException('Respuesta OAuth2 de Bancaribe inválida: falta access_token.', 502);
        }

        $expiresIn = (int)($data['expires_in'] ?? 3600);
        $expiresAt = (new DateTimeImmutable())->modify("+{$expiresIn} seconds");
        $accessToken = (string)$data['access_token'];
        $tokenType = (string)($data['token_type'] ?? 'Bearer');

        try {
            $this->tokenRepository->upsert('bancaribe', $accessToken, $tokenType, $expiresAt);
        } catch (Throwable) {
            // No interrumpir si ocurre un error transitorio de BD
        }

        $this->cacheToken($accessToken, max(60, $expiresIn - 60));

        return $accessToken;
    }

    private function cacheToken(string $token, int $ttl): void
    {
        if ($this->redis === null) {
            return;
        }
        try {
            $this->redis->execute('SETEX', self::CACHE_KEY, (string)$ttl, $token);
        } catch (Throwable) {
        }
    }
}
