<?php

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Http\HttpClientInterface;
use App\Infrastructure\Http\HttpResponse;
use App\Infrastructure\Services\PusherBeamsService;
use PHPUnit\Framework\TestCase;

class PusherBeamsServiceTest extends TestCase
{
    public function testPublishToInterestsSendsCorrectDualPayloadToPusherBeamsApi(): void
    {
        $mockHttp = $this->createMock(HttpClientInterface::class);

        $mockHttp->expects($this->once())
            ->method('postJson')
            ->willReturn(new HttpResponse(200, [], json_encode(['publishId' => 'pub-12345'])));

        $service = new PusherBeamsService(
            httpClient: $mockHttp,
            instanceId: 'test_instance_123',
            secretKey: 'test_secret_key_456'
        );

        $success = $service->publishToInterests(
            interests: ['cranes-radar'],
            title: '🚨 ¡NUEVO AUXILIO VIAL DISPONIBLE!',
            body: 'Avería en Av. Francisco de Miranda',
            data: ['service_uuid' => '00000000-0000-0000-0000-000000000001']
        );

        $this->assertTrue($success);
    }

    public function testReturnsFalseGracefullyWhenCredentialsAreEmpty(): void
    {
        $mockHttp = $this->createMock(HttpClientInterface::class);
        $mockHttp->expects($this->never())->method('postJson');

        $service = new PusherBeamsService(
            httpClient: $mockHttp,
            instanceId: '',
            secretKey: ''
        );

        $this->assertFalse($service->publishToInterests(['cranes-radar'], 'Title', 'Body'));
    }
}
