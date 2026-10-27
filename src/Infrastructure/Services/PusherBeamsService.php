<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Domain\Shared\Services\{IntegrationLoggerInterface, PushNotificationInterface};
use App\Infrastructure\Http\HttpClientInterface;
use Throwable;

class PusherBeamsService implements PushNotificationInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $instanceId = '',
        private string $secretKey = '',
        private ?IntegrationLoggerInterface $logger = null
    ) {}

    public function publishToInterests(array $interests, string $title, string $body, array $data = []): bool
    {
        if (empty($this->instanceId) || empty($this->secretKey) || empty($interests)) {
            return false;
        }

        $url = "https://{$this->instanceId}.pushnotifications.pusher.com/publish_api/v1/instances/{$this->instanceId}/publishes";

        // Formatear payload dual compatible nativamente con Android (FCM) e iOS (APNs)
        $payload = [
            'interests' => array_values($interests),
            'fcm' => [
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                    'sound' => 'default',
                ],
                'data' => array_map('strval', $data),
            ],
            'apns' => [
                'aps' => [
                    'alert' => [
                        'title' => $title,
                        'body'  => $body,
                    ],
                    'sound' => 'default',
                ],
                'data' => $data,
            ],
        ];

        $headers = [
            'Authorization' => "Bearer {$this->secretKey}",
        ];

        $startTime = microtime(true);
        try {
            $response = $this->httpClient->postJson($url, $payload, $headers);
            $durationMs = (microtime(true) - $startTime) * 1000;

            $this->logger?->log(
                service: 'pusher_beams',
                operation: 'publishToInterests',
                url: $url,
                statusCode: $response->status(),
                durationMs: $durationMs,
                requestPayload: $payload,
                responsePayload: $response->body(),
                error: $response->isSuccessful() ? null : "HTTP {$response->status()}"
            );

            return $response->isSuccessful();
        } catch (Throwable $e) {
            $durationMs = (microtime(true) - $startTime) * 1000;
            $this->logger?->log(
                service: 'pusher_beams',
                operation: 'publishToInterests_failed',
                url: $url,
                statusCode: 500,
                durationMs: $durationMs,
                requestPayload: $payload,
                responsePayload: null,
                error: $e->getMessage()
            );
            return false;
        }
    }

    public function publishToUsers(array $userIds, string $title, string $body, array $data = []): bool
    {
        if (empty($this->instanceId) || empty($this->secretKey) || empty($userIds)) {
            return false;
        }

        $url = "https://{$this->instanceId}.pushnotifications.pusher.com/publish_api/v1/instances/{$this->instanceId}/publishes/users";

        $payload = [
            'users' => array_values($userIds),
            'fcm' => [
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                    'sound' => 'default',
                ],
                'data' => array_map('strval', $data),
            ],
            'apns' => [
                'aps' => [
                    'alert' => [
                        'title' => $title,
                        'body'  => $body,
                    ],
                    'sound' => 'default',
                ],
                'data' => $data,
            ],
        ];

        $headers = [
            'Authorization' => "Bearer {$this->secretKey}",
        ];

        $startTime = microtime(true);
        try {
            $response = $this->httpClient->postJson($url, $payload, $headers);
            $durationMs = (microtime(true) - $startTime) * 1000;

            $this->logger?->log(
                service: 'pusher_beams',
                operation: 'publishToUsers',
                url: $url,
                statusCode: $response->status(),
                durationMs: $durationMs,
                requestPayload: $payload,
                responsePayload: $response->body(),
                error: $response->isSuccessful() ? null : "HTTP {$response->status()}"
            );

            return $response->isSuccessful();
        } catch (Throwable $e) {
            $durationMs = (microtime(true) - $startTime) * 1000;
            $this->logger?->log(
                service: 'pusher_beams',
                operation: 'publishToUsers_failed',
                url: $url,
                statusCode: 500,
                durationMs: $durationMs,
                requestPayload: $payload,
                responsePayload: null,
                error: $e->getMessage()
            );
            return false;
        }
    }
}
