<?php

declare(strict_types=1);

namespace App\Domain\Shared\Services;

interface IntegrationLoggerInterface
{
    /**
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
    ): void;

    /**
     * @return list<array<string, mixed>>
     */
    public function readLogs(?string $service = null, int $limit = 50): array;
}
