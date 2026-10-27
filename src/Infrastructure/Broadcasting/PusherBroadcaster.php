<?php

namespace App\Infrastructure\Broadcasting;

use App\Domain\Shared\Services\{BroadcasterInterface, IntegrationLoggerInterface};
use App\Infrastructure\Http\HttpClientInterface;
use Throwable;

class PusherBroadcaster implements BroadcasterInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $appId,
        private string $appKey,
        private string $appSecret,
        private string $cluster = 'sa1',
        private int $port = 443,
        private string $scheme = 'https',
        private ?IntegrationLoggerInterface $logger = null
    ) {}

    /**
     * Emite un evento en tiempo real a través de la API REST oficial de Pusher Channels (FR-23, FR-25, FR-26).
     *
     * @param string|array<string> $channels
     * @param string $event
     * @param array<string, mixed> $data
     * @return bool
     */
    public function broadcast(string|array $channels, string $event, array $data): bool
    {
        if (empty($this->appId) || empty($this->appKey) || empty($this->appSecret)) {
            return false;
        }

        $startTime = microtime(true);
        $url = '';
        $payload = [];

        try {
            $channelList = is_array($channels) ? array_values($channels) : [$channels];
            $encodedData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $payload = [
                'name'     => $event,
                'channels' => $channelList,
                'data'     => $encodedData,
            ];

            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $bodyMd5 = md5($body);
            $timestamp = (string)time();
            $path = "/apps/{$this->appId}/events";

            // Parámetros de autenticación HMAC ordenados alfabéticamente
            $queryParams = [
                'auth_key'       => $this->appKey,
                'auth_timestamp' => $timestamp,
                'auth_version'   => '1.0',
                'body_md5'       => $bodyMd5,
            ];
            ksort($queryParams);
            $queryString = http_build_query($queryParams);

            $stringToSign = "POST\n{$path}\n{$queryString}";
            $signature = hash_hmac('sha256', $stringToSign, $this->appSecret);

            $host = $this->cluster !== '' ? "api-{$this->cluster}.pusher.com" : 'api.pusherapp.com';
            $portStr = ($this->port === 80 || $this->port === 443) ? '' : ":{$this->port}";
            $url = "{$this->scheme}://{$host}{$portStr}{$path}?{$queryString}&auth_signature={$signature}";

            $response = $this->httpClient->postJson(
                url: $url,
                data: $payload,
                headers: [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ]
            );

            return $response->isSuccessful();
        } catch (Throwable $e) {
            $durationMs = (microtime(true) - $startTime) * 1000;
            $this->logger?->log(
                service: 'pusher',
                operation: 'broadcast_event',
                url: $url !== '' ? $url : "{$this->scheme}://api-{$this->cluster}.pusher.com/apps/{$this->appId}/events",
                statusCode: 500,
                durationMs: $durationMs,
                requestPayload: $payload !== [] ? $payload : ['event' => $event, 'channels' => $channels],
                responsePayload: null,
                error: $e->getMessage()
            );
            return false;
        }
    }

    /**
     * Firma la suscripción a canales privados o de presencia según el protocolo estándar de Pusher (OWASP Security).
     *
     * @param string $channelName
     * @param string $socketId
     * @param array<string, mixed>|null $channelData
     * @return array<string, string>
     */
    public function authorizeChannel(string $channelName, string $socketId, ?array $channelData = null): array
    {
        if (str_starts_with($channelName, 'presence-') && $channelData !== null) {
            $dataEncoded = json_encode($channelData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $stringToSign = "{$socketId}:{$channelName}:{$dataEncoded}";
            $signature = hash_hmac('sha256', $stringToSign, $this->appSecret);

            return [
                'auth'         => "{$this->appKey}:{$signature}",
                'channel_data' => (string)$dataEncoded,
            ];
        }

        $stringToSign = "{$socketId}:{$channelName}";
        $signature = hash_hmac('sha256', $stringToSign, $this->appSecret);

        return [
            'auth' => "{$this->appKey}:{$signature}",
        ];
    }
}
