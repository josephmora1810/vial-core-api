<?php

declare(strict_types=1);

namespace Tests\Unit\Middlewares;

use App\Core\{Request, Response};
use App\Infrastructure\Database\Redis;
use App\Middlewares\ThrottleMiddleware;
use PHPUnit\Framework\TestCase;

final class ThrottleMiddlewareTest extends TestCase
{
    private Redis $redis;

    protected function setUp(): void
    {
        Response::$shouldExit = false;
        $this->redis = $this->createStub(Redis::class);
    }

    protected function tearDown(): void
    {
        Response::$shouldExit = true;
    }

    public function testAllowsRequestUnderLimit(): void
    {
        $this->redis->method('execute')->willReturn(1);

        $middleware = new ThrottleMiddleware($this->redis);
        $request = new Request([], [], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/v1/ping']);

        $nextCalled = false;
        $middleware->handle($request, function () use (&$nextCalled) {
            $nextCalled = true;
            return 'passed';
        });

        $this->assertTrue($nextCalled);
    }

    public function testThrottlesWhenExceedingLimit(): void
    {
        $this->redis->method('execute')->willReturn(100); // Over limit 60

        $middleware = new ThrottleMiddleware($this->redis);
        $request = new Request([], [], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/v1/ping']);

        $nextCalled = false;
        ob_start();
        try {
            $middleware->handle($request, function () use (&$nextCalled) {
                $nextCalled = true;
            });
        } finally {
            $output = ob_get_clean();
        }

        $this->assertFalse($nextCalled);
        $data = json_decode((string)$output, true);
        $this->assertSame(429, $data['code']);
    }
}
