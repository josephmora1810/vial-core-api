<?php

namespace App\Infrastructure\Logging;

use DateTimeImmutable;
use Throwable;

class IntegrationLogger
{
    private string $logFilePath;

    public function __construct(?string $logFilePath = null)
    {
        $this->logFilePath = $logFilePath ?? dirname(__DIR__, 3) . '/storage/logs/integrations.jsonl';
    }

    /**
     * Registra una llamada a un servicio de terceros (OCR, Bancaribe, Mapbox, Pusher, S3, etc.)
     *
     * @param array<string, mixed>|string|null $requestPayload
     * @param array<string, mixed>|string|null $responsePayload
     */
    public function log(
        string $service,
        string $operation,
        string $url,
        int $statusCode,
        float $durationMs,
        array|string|null $requestPayload = null,
        array|string|null $responsePayload = null,
        ?string $error = null
    ): void {
        try {
            $dir = dirname($this->logFilePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            $entry = [
                'timestamp'        => (new DateTimeImmutable())->format('Y-m-d H:i:s.v'),
                'service'          => strtolower(trim($service)),
                'operation'        => $operation,
                'url'              => $url,
                'status_code'      => $statusCode,
                'duration_ms'      => round($durationMs, 2),
                'request_payload'  => $this->normalizePayload($requestPayload),
                'response_payload' => $this->normalizePayload($responsePayload),
                'error'            => $error,
            ];

            file_put_contents(
                $this->logFilePath,
                json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        } catch (Throwable) {
            // Nunca interrumpir el flujo transaccional si falla la escritura de archivo
        }
    }

    /**
     * Detecta el nombre del servicio externo a partir de su URL
     */
    public static function detectServiceFromUrl(string $url): string
    {
        $lower = strtolower($url);
        if (str_contains($lower, 'bancaribe') || str_contains($lower, 'bcvmicro') || str_contains($lower, 'c2p') || str_contains($lower, 'consulta-operacion') || str_contains($lower, 'ctasconsultasaldos')) {
            return 'bancaribe';
        }
        if (str_contains($lower, 'extract') || str_contains($lower, ':8080') || str_contains($lower, 'ocr')) {
            return 'ocr';
        }
        if (str_contains($lower, 'mapbox')) {
            return 'mapbox';
        }
        if (str_contains($lower, 'pusher')) {
            return 'pusher';
        }
        if (str_contains($lower, 'amazonaws.com') || str_contains($lower, 's3')) {
            return 's3';
        }
        return 'external_http';
    }

    /**
     * Lee los últimos registros de integraciones de terceros (ordenados del más reciente al más antiguo)
     *
     * @return list<array<string, mixed>>
     */
    public function readLogs(?string $service = null, int $limit = 50): array
    {
        if (!is_file($this->logFilePath) || !is_readable($this->logFilePath)) {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $serviceFilter = $service !== null && trim($service) !== '' ? strtolower(trim($service)) : null;

        $lines = file($this->logFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return [];
        }

        $entries = [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $decoded = json_decode($lines[$i], true);
            if (!is_array($decoded)) {
                continue;
            }
            if ($serviceFilter !== null && !str_contains((string)($decoded['service'] ?? ''), $serviceFilter)) {
                continue;
            }
            $entries[] = $decoded;
            if (count($entries) >= $limit) {
                break;
            }
        }

        return $entries;
    }

    private function normalizePayload(array|string|null $payload): mixed
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                return $this->maskSensitiveFields($decoded);
            }
            return mb_substr($payload, 0, 1000);
        }
        if (is_array($payload)) {
            return $this->maskSensitiveFields($payload);
        }
        return null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function maskSensitiveFields(array $data): array
    {
        $masked = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string)$key);
            if (in_array($lowerKey, ['authorization', 'x-api-key', 'client_secret', 'password', 'otp'], true)) {
                $masked[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $masked[$key] = $this->maskSensitiveFields($value);
            } elseif (is_object($value)) {
                $masked[$key] = '[Object ' . get_class($value) . ']';
            } else {
                $masked[$key] = $value;
            }
        }
        return $masked;
    }
}
