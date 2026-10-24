<?php

declare(strict_types=1);

namespace Tests\Unit\Middlewares;

use App\Core\{JwtService, Request};
use App\Domain\Auth\{Entities\Role, ValueObjects\Password};
use App\Domain\Shared\ValueObjects\{Money, Uuid};
use App\Domain\Users\Entities\User;
use App\Domain\Users\Repositories\UserRepositoryInterface;
use App\Domain\Users\ValueObjects\{
    BirthDate,
    Email,
    Names,
    NationalId,
    Phone,
    Surnames
};
use App\Middlewares\AuthMiddleware;
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;

final class AuthMiddlewareTest extends TestCase
{
    private JwtService $jwtService;
    private UserRepositoryInterface $userRepo;
    private array $savedServer;

    protected function setUp(): void
    {
        $this->savedServer = $_SERVER;
        $this->jwtService = new JwtService();
        $this->userRepo = $this->createStub(UserRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->savedServer;
    }

    private function createUser(int $id = 1): User
    {
        return new User(
            id: $id,
            uuid: Uuid::generate(),
            names: new Names('Juan'),
            surnames: new Surnames('Perez'),
            email: new Email('juan@example.com'),
            password: Password::fromPlainText('SecurePass123!'),
            phone: new Phone('+584121234567'),
            walletBalance: new Money(0, 'VES'),
            identificationCard: new NationalId('V-12345678'),
            birthDate: new BirthDate('1990-01-01'),
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            consentAt: new DateTimeImmutable(),
            deletedAt: null,
            isActive: true,
            roles: [new Role(1, 'client')]
        );
    }

    public function testAllowsPublicRouteWithoutToken(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/v1/ping';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $middleware = new AuthMiddleware($this->jwtService, $this->userRepo, ['/api/v1/ping']);
        $request = new Request();

        $nextCalled = false;
        $res = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return 'passed';
        });

        $this->assertTrue($nextCalled);
        $this->assertSame('passed', $res);
    }

    public function testThrowsWhenNoTokenProvided(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/v1/me';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $middleware = new AuthMiddleware($this->jwtService, $this->userRepo);
        $request = new Request();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(401);
        $this->expectExceptionMessage('Token de autenticación no proporcionado.');

        $middleware->handle($request, fn() => null);
    }

    public function testThrowsWhenUserNotFound(): void
    {
        $user = $this->createUser();
        $token = $this->jwtService->generateToken(['sub' => $user->getUuid()->getValue()]);

        $_SERVER['REQUEST_URI'] = '/api/v1/me';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        $this->userRepo->method('findByUuid')->willReturn(null);

        $middleware = new AuthMiddleware($this->jwtService, $this->userRepo);
        $request = new Request();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(401);
        $this->expectExceptionMessage('Usuario no encontrado o cuenta desactivada.');

        $middleware->handle($request, fn() => null);
    }

    public function testAttachesUserToRequestOnSuccess(): void
    {
        $user = $this->createUser();
        $token = $this->jwtService->generateToken(['sub' => $user->getUuid()->getValue()]);

        $_SERVER['REQUEST_URI'] = '/api/v1/me';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        $this->userRepo->method('findByUuid')->willReturn($user);

        $middleware = new AuthMiddleware($this->jwtService, $this->userRepo);
        $request = new Request();

        $nextCalled = false;
        $middleware->handle($request, function (Request $req) use (&$nextCalled, $user) {
            $nextCalled = true;
            $this->assertSame($user, $req->user());
            return 'success';
        });

        $this->assertTrue($nextCalled);
    }
}
