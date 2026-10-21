<?php

namespace App\Infrastructure\Services\Bancaribe;

use App\Domain\Payments\Services\BalanceGatewayInterface;
use App\Infrastructure\Database\Redis;
use App\Infrastructure\Http\HttpClientInterface;
use Throwable;

class BancaribeBalanceService implements BalanceGatewayInterface
{
    private const CACHE_TTL = 300; // 5 minutos

    public function __construct(
        private BancaribeAuthService $authService,
        private HttpClientInterface $httpClient,
        private ?Redis $redis = null,
        private string $baseUrl = '',
        private string $checkBalanceEndpoint = 'Cuentas/ConsultaSaldo/1.0.0/ctasConsultaSaldos',
        private string $checkBalanceHash = '',
        private string $rif = 'J502098500',
        private bool $sandboxMode = false
    ) {}

    public function getBalance(?string $accountNumber = null, bool $forceRefresh = false): array
    {
        $cacheKey = $accountNumber ? "bancaribe_balance_cache_{$accountNumber}" : 'bancaribe_balance_cache';

        if ($forceRefresh) {
            $this->forgetCache($cacheKey);
        } else {
            $cached = $this->getFromCache($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $token = $this->authService->getAccessToken();
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($this->checkBalanceEndpoint, '/');
        $clienteHash = base64_encode("{$token}:{$this->checkBalanceHash}");

        $payload = [
            'canal'        => 'api',
            'clienteHash'  => $clienteHash,
            'clienteRif'   => $this->rif,
            'numeroCuenta' => $accountNumber,
            'requestIp'    => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ];

        if ($this->sandboxMode) {
            $sandboxResult = [
                'success'  => true,
                'balance'  => 145820.75,
                'accounts' => [
                    [
                        'account_number' => $accountNumber ?: '01140150991234567890',
                        'currency_code'  => 'VES',
                        'currency_name'  => 'Bolivares',
                        'available'      => 145820.75,
                        'balance'        => 145820.75,
                        'blocked'        => 0.00,
                    ],
                ],
                'raw_data' => ['sandbox' => true],
            ];
            $this->putInCache($cacheKey, $sandboxResult);
            return $sandboxResult;
        }

        try {
            $response = $this->httpClient->postJson(
                url: $url,
                data: $payload,
                headers: [
                    'Authorization' => "Bearer {$token}",
                    'Accept'        => 'application/json',
                ]
            );
        } catch (Throwable $e) {
            return [
                'success' => false,
                'balance' => 0.0,
                'message' => 'Error de conexión con Bancaribe: ' . $e->getMessage(),
            ];
        }

        $responseBody = $response->json();

        if (!$response->isSuccessful()) {
            $errorMsg = is_array($responseBody)
                ? ($responseBody['descripcionError'] ?? $responseBody['error'] ?? $responseBody['message'] ?? $response->body())
                : $response->body();

            return [
                'success' => false,
                'balance' => 0.0,
                'message' => "Error del banco: {$errorMsg}",
            ];
        }

        if (!isset($responseBody['data']) || !is_array($responseBody['data'])) {
            if (isset($responseBody['producto'])) {
                $responseBody['data'] = [$responseBody];
            } else {
                return [
                    'success' => false,
                    'balance' => 0.0,
                    'message' => 'Respuesta del banco con formato inesperado.',
                ];
            }
        }

        $accounts = [];
        $totalBalance = 0.0;

        foreach ($responseBody['data'] as $account) {
            $available = (float)($account['saldo']['disponible'] ?? 0);
            $accounts[] = [
                'account_number' => (string)($account['producto']['numeroCuenta'] ?? 'N/A'),
                'currency_code'  => (string)($account['moneda']['codigo'] ?? 'VES'),
                'currency_name'  => (string)($account['moneda']['descripcion'] ?? 'Bolivares'),
                'available'      => $available,
                'balance'        => (float)($account['saldo']['contable'] ?? 0),
                'blocked'        => (float)($account['saldo']['bloqueado'] ?? 0),
            ];
            $totalBalance += $available;
        }

        $result = [
            'success'  => true,
            'balance'  => round($totalBalance, 2),
            'accounts' => $accounts,
            'raw_data' => $responseBody,
        ];

        $this->putInCache($cacheKey, $result);

        return $result;
    }

    private function getFromCache(string $key): ?array
    {
        if ($this->redis === null) {
            return null;
        }
        try {
            $raw = $this->redis->execute('GET', $key);
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        } catch (Throwable) {
        }
        return null;
    }

    private function putInCache(string $key, array $data): void
    {
        if ($this->redis === null) {
            return;
        }
        try {
            $this->redis->execute('SETEX', $key, (string)self::CACHE_TTL, json_encode($data));
        } catch (Throwable) {
        }
    }

    private function forgetCache(string $key): void
    {
        if ($this->redis === null) {
            return;
        }
        try {
            $this->redis->execute('DEL', $key);
        } catch (Throwable) {
        }
    }
}
