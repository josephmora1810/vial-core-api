<?php

declare(strict_types=1);

namespace Tests\Unit\Middlewares;

use App\Core\Request;
use App\Middlewares\CorsMiddleware;
use PHPUnit\Framework\TestCase;

final class CorsMiddlewareTest extends TestCase
{
    public function testPassesStandardRequest(): void
    {
        $middleware = new CorsMiddleware();
        $request = new Request([], [], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/v1/ping']);

        $nextCalled = false;
        $res = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return 'ok';
        });

        $this->assertTrue($nextCalled);
        $this->assertSame('ok', $res);
    }
}
