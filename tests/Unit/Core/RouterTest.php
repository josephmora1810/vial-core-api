<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\{Request, Router};
use Exception;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;
    private array $savedServer;

    protected function setUp(): void
    {
        $this->savedServer = $_SERVER;
        $this->router = new Router();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->savedServer;
    }

    public function testRegisterAndResolveGetRoute(): void
    {
        $this->router->get('/api/v1/ping', 'HomeController@index');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/v1/ping';

        $request = new Request();
        $match = $this->router->resolve($request);

        $this->assertSame('HomeController@index', $match['action']);
        $this->assertSame([], $match['params']);
    }

    public function testResolveRouteWithParameters(): void
    {
        $this->router->get('/api/v1/vehicles/{id}', 'VehicleController@show', ['auth']);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/v1/vehicles/uuid-xyz-123';

        $request = new Request();
        $match = $this->router->resolve($request);

        $this->assertSame('VehicleController@show', $match['action']);
        $this->assertSame(['auth'], $match['middlewares']);
        $this->assertSame('uuid-xyz-123', $match['params']['id']);
    }

    public function testResolveUnregisteredRouteThrows404(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/not-found';

        $request = new Request();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);

        $this->router->resolve($request);
    }
}
