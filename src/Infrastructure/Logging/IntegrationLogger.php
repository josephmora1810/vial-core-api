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
     * Registra una llamada a un servicio de terceros (OCR, Bancaribe, OSRM, Pusher, S3, etc.)
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

    /** @var array<string, list<string>> */
    private static array $serviceKeywords = [
        'bancaribe' => ['bancaribe', 'bcvmicro', 'c2p', 'consulta-operacion', 'ctasconsultasaldos'],
        'ocr'       => ['extract', ':8080', 'ocr'],
        'osrm'      => ['osrm', 'project-osrm.org', ':5000'],
        'pusher'    => ['pusher'],
        's3'        => ['amazonaws.com', 's3'],
    ];

    /**
     * Permite a cualquier módulo registrar o extender los patrones de detección
     * de servicios externos sin modificar esta clase (Principio Abierto/Cerrado - OCP).
     *
     * @param string $service Nombre clave del servicio (ej. 'osrm', 'twilio')
     * @param list<string> $keywords Fragmentos de URL que identifican al servicio
     */
    public static function registerServicePattern(string $service, array $keywords): void
    {
        $service = strtolower(trim($service));
        self::$serviceKeywords[$service] = array_values(array_unique(array_merge(
            self::$serviceKeywords[$service] ?? [],
            array_map('strtolower', $keywords)
        )));
    }

    /**
     * Detecta el nombre del servicio externo a partir de su URL.
     * Si no coincide con ninguna palabra clave registrada, extrae el host (dominio)
     * como fallback genérico resiliente.
     */
    public static function detectServiceFromUrl(string $url): string
    {
        $lower = strtolower($url);

        foreach (self::$serviceKeywords as $service => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    return $service;
                }
            }
        }

        $host = parse_url($url, PHP_URL_HOST);
        return $host ? strtolower($host) : 'external_http';
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
