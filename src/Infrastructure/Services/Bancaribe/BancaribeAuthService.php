<?php

namespace App\Infrastructure\Services\Bancaribe;

use App\Domain\Payments\Repositories\BankTokenRepositoryInterface;
use App\Infrastructure\Database\Redis;
use App\Infrastructure\Http\HttpClientInterface;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

class BancaribeAuthService
{
    private const CACHE_KEY = 'bancaribe_access_token';

    public function __construct(
        private HttpClientInterface $httpClient,
        private BankTokenRepositoryInterface $tokenRepository,
        private ?Redis $redis = null,
        private string $baseAuthUrl = '',
        private string $oauth2Endpoint = 'oauth2/token',
        private string $customerKey = '',
        private string $customerSecret = '',
        private bool $sandboxMode = false
    ) {}

    public function getAccessToken(): string
    {
        // 1. Consultar caché en Redis
        if ($this->redis !== null) {
            try {
                $cachedToken = $this->redis->execute('GET', self::CACHE_KEY);
                if (is_string($cachedToken) && $cachedToken !== '') {
                    return $cachedToken;
                }
            } catch (Throwable) {
                // Continuar a BD si Redis falla
            }
        }

        // 2. Consultar tabla bank_tokens en MariaDB
        $record = $this->tokenRepository->findByGateway('bancaribe');
        $now = new DateTimeImmutable();

        if ($record !== null && $record['expires_at'] !== null && $record['expires_at'] > $now) {
            $ttl = max(60, $record['expires_at']->getTimestamp() - $now->getTimestamp() - 60);
            $this->cacheToken($record['access_token'], $ttl);
            return $record['access_token'];
        }

        // 3. Solicitar nuevo token OAuth2 al banco
        return $this->requestNewAccessToken();
    }

    public function requestNewAccessToken(): string
    {
        if ($this->sandboxMode) {
            $fakeToken = 'sandbox_bancaribe_token_' . bin2hex(random_bytes(8));
            $expiresAt = (new DateTimeImmutable())->modify('+3600 seconds');
            $this->tokenRepository->upsert('bancaribe', $fakeToken, 'Bearer', $expiresAt);
            $this->cacheToken($fakeToken, 3540);
            return $fakeToken;
        }

        $url = rtrim($this->baseAuthUrl, '/') . '/' . ltrim($this->oauth2Endpoint, '/');
        $basicAuth = base64_encode("{$this->customerKey}:{$this->customerSecret}");

        try {
            $response = $this->httpClient->postJson(
                url: $url,
                data: ['grant_type' => 'client_credentials'],
                headers: [
                    'Authorization' => "Basic {$basicAuth}",
                    'Accept'        => 'application/json',
                ]
            );
        } catch (Throwable $e) {
            throw new RuntimeException('Error de red al obtener el token de acceso de Bancaribe: ' . $e->getMessage(), 502, $e);
        }

        if (!$response->isSuccessful()) {
            throw new RuntimeException('Error al obtener el token de acceso de Bancaribe: ' . $response->body(), 502);
        }

        $data = $response->json();
        if (empty($data['access_token'])) {
            throw new RuntimeException('Respuesta OAuth2 de Bancaribe inválida: falta access_token.', 502);
        }

        $expiresIn = (int)($data['expires_in'] ?? 3600);
        $expiresAt = (new DateTimeImmutable())->modify("+{$expiresIn} seconds");
        $accessToken = (string)$data['access_token'];
        $tokenType = (string)($data['token_type'] ?? 'Bearer');

        $this->tokenRepository->upsert('bancaribe', $accessToken, $tokenType, $expiresAt);
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
