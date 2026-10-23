<?php

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Broadcasting\PusherBroadcaster;
use App\Infrastructure\Http\HttpClientInterface;
use App\Infrastructure\Http\HttpResponse;
use App\Infrastructure\Logging\IntegrationLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PusherBroadcasterTest extends TestCase
{
    public function testBroadcastSendsAuthorizedPostRequestToPusherRestApi(): void
    {
        $mockHttpClient = $this->createMock(HttpClientInterface::class);

        $capturedUrl = null;
        $capturedPayload = null;

        $mockHttpClient->expects($this->once())
            ->method('postJson')
            ->willReturnCallback(function (string $url, array $data, array $_) use (&$capturedUrl, &$capturedPayload) {
                $capturedUrl = $url;
                $capturedPayload = $data;

                return new HttpResponse(status: 200, headers: [], body: '{}');
            });

        $broadcaster = new PusherBroadcaster(
            httpClient: $mockHttpClient,
            appId: '2090031',
            appKey: '0a344aecc98f5b70c1f4',
            appSecret: '9ce96ea9dc229c50a67e',
            cluster: 'sa1',
            port: 443,
            scheme: 'https'
        );

        $success = $broadcaster->broadcast(
            channels: ['cranes-radar'],
            event: 'new-sos-request',
            data: ['id' => '0192e21b-85fa-7090-bb97-b35583b4b576', 'plate' => 'AB123CD']
        );

        $this->assertTrue($success);
        $this->assertNotNull($capturedUrl);

        // Validar URL y parámetros HMAC requeridos por Pusher
        $this->assertStringContainsString('https://api-sa1.pusher.com/apps/2090031/events?', $capturedUrl);
        $this->assertStringContainsString('auth_key=0a344aecc98f5b70c1f4', $capturedUrl);
        $this->assertStringContainsString('auth_version=1.0', $capturedUrl);
        $this->assertStringContainsString('body_md5=', $capturedUrl);
        $this->assertStringContainsString('auth_signature=', $capturedUrl);

        // Validar estructura del payload
        $this->assertSame('new-sos-request', $capturedPayload['name']);
        $this->assertSame(['cranes-radar'], $capturedPayload['channels']);

        $innerData = json_decode($capturedPayload['data'], true);
        $this->assertSame('AB123CD', $innerData['plate']);
    }

    public function testBroadcastReturnsFalseWhenMissingCredentials(): void
    {
        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->expects($this->never())->method('postJson');

        $broadcaster = new PusherBroadcaster(
            httpClient: $mockHttpClient,
            appId: '',
            appKey: '',
            appSecret: ''
        );

        $result = $broadcaster->broadcast('channel', 'event', ['test' => 1]);
        $this->assertFalse($result);
    }

    public function testBroadcastLogsExceptionWithAllRequiredArgumentsWhenHttpClientFails(): void
    {
        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->expects($this->once())
            ->method('postJson')
            ->willThrowException(new RuntimeException('Connection timeout to Pusher cluster'));

        $mockLogger = $this->createMock(IntegrationLogger::class);
        $mockLogger->expects($this->once())
            ->method('log')
            ->with(
                'pusher',
                'broadcast_event',
                $this->stringContains('https://api-sa1.pusher.com/apps/2090031/events'),
                500,
                $this->callback(fn($val) => is_float($val) || is_numeric($val)),
                $this->callback(fn($val) => is_array($val)),
                null,
                $this->stringContains('Connection timeout')
            );

        $broadcaster = new PusherBroadcaster(
            httpClient: $mockHttpClient,
            appId: '2090031',
            appKey: '0a344aecc98f5b70c1f4',
            appSecret: '9ce96ea9dc229c50a67e',
            cluster: 'sa1',
            port: 443,
            scheme: 'https',
            logger: $mockLogger
        );

        $result = $broadcaster->broadcast(
            channels: 'cranes-radar',
            event: 'new-sos-request',
            data: ['id' => '0192e21b-85fa-7090-bb97-b35583b4b576']
        );

        $this->assertFalse($result);
    }

    public function testAuthorizePrivateChannelGeneratesValidSignature(): void
    {
        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $broadcaster = new PusherBroadcaster(
            httpClient: $mockHttpClient,
            appId: '12345',
            appKey: 'my-key',
            appSecret: 'my-secret',
            cluster: 'sa1'
        );

        $authData = $broadcaster->authorizeChannel('private-cranes-radar', '123.456');

        $this->assertArrayHasKey('auth', $authData);
        $expectedSignature = hash_hmac('sha256', '123.456:private-cranes-radar', 'my-secret');
        $this->assertSame("my-key:{$expectedSignature}", $authData['auth']);
    }

    public function testAuthorizePresenceChannelIncludesChannelData(): void
    {
        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $broadcaster = new PusherBroadcaster(
            httpClient: $mockHttpClient,
            appId: '12345',
            appKey: 'my-key',
            appSecret: 'my-secret',
            cluster: 'sa1'
        );

        $userData = ['user_id' => 1, 'user_info' => ['name' => 'Chofer 1']];
        $authData = $broadcaster->authorizeChannel('presence-chat', '123.456', $userData);

        $this->assertArrayHasKey('auth', $authData);
        $this->assertArrayHasKey('channel_data', $authData);
        $encoded = json_encode($userData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $expectedSignature = hash_hmac('sha256', "123.456:presence-chat:{$encoded}", 'my-secret');
        $this->assertSame("my-key:{$expectedSignature}", $authData['auth']);
    }
}
