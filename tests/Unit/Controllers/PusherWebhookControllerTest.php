<?php

namespace Tests\Unit\Controllers;

use App\Controllers\PusherWebhookController;
use App\Core\Request;
use App\Core\Response;
use PHPUnit\Framework\TestCase;

class PusherWebhookControllerTest extends TestCase
{
    private string $appKey = 'test_app_key_123';
    private string $appSecret = 'test_app_secret_456';
    private PusherWebhookController $controller;

    protected function setUp(): void
    {
        Response::$shouldExit = false;
        unset($_SERVER['HTTP_X_PUSHER_KEY'], $_SERVER['HTTP_X_PUSHER_SIGNATURE']);
        $this->controller = new PusherWebhookController(
            appKey: $this->appKey,
            appSecret: $this->appSecret
        );
    }

    protected function tearDown(): void
    {
        Response::$shouldExit = true;
        unset($_SERVER['HTTP_X_PUSHER_KEY'], $_SERVER['HTTP_X_PUSHER_SIGNATURE']);
    }

    public function testRejectsRequestWithoutHeaders(): void
    {
        $request = new Request();

        ob_start();
        $this->controller->handle($request);
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertSame('fail', $data['status']);
        $this->assertSame(401, http_response_code());
    }

    public function testRejectsRequestWithInvalidSignature(): void
    {
        $payload = ['events' => [['name' => 'channel_occupied', 'channel' => 'cranes-radar']]];
        $body = json_encode($payload);

        $_SERVER['HTTP_X_PUSHER_KEY'] = $this->appKey;
        $_SERVER['HTTP_X_PUSHER_SIGNATURE'] = 'invalid_signature_hash';

        $request = new Request();

        ob_start();
        $this->controller->handle($request, $body);
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertSame('fail', $data['status']);
        $this->assertSame(401, http_response_code());
    }

    public function testProcessesValidChannelExistenceWebhook(): void
    {
        $payload = [
            'time_ms' => 1729660000000,
            'events'  => [
                ['name' => 'channel_occupied', 'channel' => 'cranes-radar'],
                ['name' => 'channel_vacated', 'channel' => 'cranes-radar'],
            ],
        ];
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $body, $this->appSecret);

        $_SERVER['HTTP_X_PUSHER_KEY'] = $this->appKey;
        $_SERVER['HTTP_X_PUSHER_SIGNATURE'] = $signature;

        $request = new Request();

        ob_start();
        $this->controller->handle($request, $body);
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertSame('success', $data['status']);
        $this->assertSame(200, http_response_code());
        $this->assertSame(2, $data['data']['processed']);
        $this->assertSame('channel_occupied', $data['data']['events'][0]['event']);
        $this->assertSame('channel_vacated', $data['data']['events'][1]['event']);
    }
}
