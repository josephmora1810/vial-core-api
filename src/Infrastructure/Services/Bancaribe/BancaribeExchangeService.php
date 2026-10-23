<?php

namespace App\Infrastructure\Services\Bancaribe;

use App\Domain\Payments\Entities\ExchangeRate;
use App\Domain\Payments\Repositories\ExchangeRateRepositoryInterface;
use App\Domain\Payments\Services\ExchangeRateServiceInterface;
use App\Infrastructure\Database\Redis;
use App\Infrastructure\Http\HttpClientInterface;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

class BancaribeExchangeService implements ExchangeRateServiceInterface
{
    private const CACHE_KEY_PREFIX = 'bancaribe_exchange_rate_';
    private const CACHE_TTL = 3600; // 1 hora en Redis

    public function __construct(
        private BancaribeAuthService $authService,
        private HttpClientInterface $httpClient,
        private ExchangeRateRepositoryInterface $rateRepository,
        private ?Redis $redis = null,
        private string $baseUrl = '',
        private string $exchangeEndpoint = 'BCVMicro/1.0.0/consultaTasa/tasa',
        private string $exchangeHash = '',
        private string $rif = '',
        private bool $isLocalEnv = true,
        private float $fallbackRate = 40.00
    ) {}

    public function getExchangeRate(string $currency = 'USD', string $type = 'venta'): float
    {
        $currency = strtoupper(trim($currency));
        $type = strtolower(trim($type));
        $cacheKey = self::CACHE_KEY_PREFIX . "{$currency}_{$type}";

        // 1. Intentar leer desde Redis (Caché Multimoneda FR-16)
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null && $cached > 0) {
            return $cached;
        }

        // 2. Buscar tasa del día de hoy en MariaDB
        $today = new DateTimeImmutable('today');
        $dbRate = $this->rateRepository->findByDate($currency, $type, $today);
        if ($dbRate !== null) {
            $this->putInCache($cacheKey, $dbRate->getRate());
            return $dbRate->getRate();
        }

        // 3. Consultar en vivo el endpoint BCV de Bancaribe y persistir
        try {
            $this->fetchAndSaveTodayRates();
            $freshRate = $this->rateRepository->findByDate($currency, $type, $today);
            if ($freshRate !== null) {
                $this->putInCache($cacheKey, $freshRate->getRate());
                return $freshRate->getRate();
            }
        } catch (Throwable) {
            // Si Bancaribe no responde, continuar a fallback en BD
        }

        // 4. Buscar última tasa registrada reciente (máximo 1 día: hoy o ayer)
        $recentThreshold = (new DateTimeImmutable('today'))->modify('-1 day');
        $fallbackDbRate = $this->rateRepository->findLatest($currency, $type, $recentThreshold);

        if ($fallbackDbRate !== null) {
            $this->putInCache($cacheKey, $fallbackDbRate->getRate());
            return $fallbackDbRate->getRate();
        }

        // 5. Fallback en entorno local para no bloquear desarrollo cuando la VPN bancaria no está activa
        if ($this->isLocalEnv) {
            $historicalDbRate = $this->rateRepository->findLatest($currency, $type);
            if ($historicalDbRate !== null) {
                $this->putInCache($cacheKey, $historicalDbRate->getRate());
                return $historicalDbRate->getRate();
            }

            $rateValue = $currency === 'EUR' ? round($this->fallbackRate * 1.08, 4) : $this->fallbackRate;
            $entity = new ExchangeRate($currency, $type, $rateValue, $today);
            $this->rateRepository->upsert($entity);
            $this->putInCache($cacheKey, $rateValue);
            return $rateValue;
        }

        throw new RuntimeException("Las tasas BCV no están disponibles en este momento. Intente más tarde.", 503);
    }

    public function fetchAndSaveTodayRates(): array
    {
        $token = $this->authService->getAccessToken();
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($this->exchangeEndpoint, '/');

        $payload = [
            'hash'        => $this->exchangeHash,
            'idTasa'      => '',
            'cedulaRif'   => $this->rif,
            'fechaInicio' => '',
            'fechaFin'    => '',
        ];

        $response = $this->httpClient->postJson(
            url: $url,
            data: $payload,
            headers: [
                'Authorization' => "Bearer {$token}",
                'Accept'        => 'application/json',
            ]
        );

        if (!$response->isSuccessful()) {
            throw new RuntimeException('Error consultando tasas BCV en Bancaribe: ' . $response->body(), 502);
        }

        $data = $response->json();
        return $this->processAndSaveRates(is_array($data) ? $data : []);
    }

    public function getAllCurrentRates(): array
    {
        // Garantizar que al menos USD compra/venta estén cargadas
        $usdVenta = $this->getExchangeRate('USD', 'venta');
        $usdCompra = $this->getExchangeRate('USD', 'compra');

        $latestAll = $this->rateRepository->findLatestAll();
        $formatted = [];

        foreach ($latestAll as $rate) {
            $formatted[] = [
                'currency' => $rate->getCurrency()->getValue(),
                'type'     => $rate->getType(),
                'rate'     => $rate->getRate(),
                'date'     => $rate->getDate()->format('Y-m-d'),
            ];
        }

        return [
            'usd_venta'  => $usdVenta,
            'usd_compra' => $usdCompra,
            'rates'      => $formatted,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array{currency: string, type: string, rate: float, date: string}>
     */
    public function processAndSaveRates(array $data): array
    {
        if (!isset($data['listTasasActuales']) || !is_array($data['listTasasActuales'])) {
            return [];
        }

        $saved = [];

        foreach ($data['listTasasActuales'] as $tasa) {
            if (!is_array($tasa)) {
                continue;
            }
            $descripcion = (string)($tasa['descripcion'] ?? '');
            $currency = $this->extractCurrency($descripcion);
            $type = $this->extractType($descripcion);
            $valor = (float)($tasa['valor'] ?? 0);

            if ($currency === null || $type === null || $valor <= 0) {
                continue;
            }

            try {
                $dateObj = !empty($tasa['fechaArch'])
                    ? new DateTimeImmutable((string)$tasa['fechaArch'])
                    : new DateTimeImmutable('today');
            } catch (Throwable) {
                $dateObj = new DateTimeImmutable('today');
            }

            $entity = new ExchangeRate($currency, $type, $valor, $dateObj);
            $this->rateRepository->upsert($entity);

            $cacheKey = self::CACHE_KEY_PREFIX . "{$currency}_{$type}";
            $this->putInCache($cacheKey, $valor);

            $saved[] = [
                'currency' => $currency,
                'type'     => $type,
                'rate'     => $valor,
                'date'     => $dateObj->format('Y-m-d'),
            ];
        }

        return $saved;
    }

    private function extractCurrency(string $descripcion): ?string
    {
        $lower = strtolower($descripcion);
        if (str_contains($lower, 'dolar')) {
            return 'USD';
        }
        if (str_contains($lower, 'euro')) {
            return 'EUR';
        }
        return null;
    }

    private function extractType(string $descripcion): ?string
    {
        $lower = strtolower($descripcion);
        if (str_contains($lower, 'compra')) {
            return 'compra';
        }
        if (str_contains($lower, 'venta')) {
            return 'venta';
        }
        return null;
    }

    private function getFromCache(string $key): ?float
    {
        if ($this->redis === null) {
            return null;
        }
        try {
            $val = $this->redis->execute('GET', $key);
            if ($val !== null && $val !== false && is_numeric($val)) {
                return (float)$val;
            }
        } catch (Throwable) {
        }
        return null;
    }

    private function putInCache(string $key, float $rate): void
    {
        if ($this->redis === null) {
            return;
        }
        try {
            $this->redis->execute('SETEX', $key, (string)self::CACHE_TTL, (string)$rate);
        } catch (Throwable) {
        }
    }
}
