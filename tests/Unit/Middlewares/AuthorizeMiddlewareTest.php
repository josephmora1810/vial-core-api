<?php

namespace Tests\Unit\Middlewares;

use App\Core\Request;
use App\Domain\Entities\Permission;
use App\Domain\Entities\Role;
use App\Domain\Entities\User;
use App\Domain\ValueObjects\BirthDate;
use App\Domain\ValueObjects\Email;
use App\Domain\ValueObjects\Money;
use App\Domain\ValueObjects\Names;
use App\Domain\ValueObjects\NationalId;
use App\Domain\ValueObjects\Password;
use App\Domain\ValueObjects\Phone;
use App\Domain\ValueObjects\Surnames;
use App\Domain\ValueObjects\Uuid;
use App\Middlewares\AuthorizeMiddleware;
use Exception;
use PHPUnit\Framework\TestCase;

class AuthorizeMiddlewareTest extends TestCase
{
    private function createUser(array $roles = [], array $permissions = []): User
    {
        return new User(
            id: 1,
            uuid: Uuid::generate(),
            names: new Names('Maria'),
            surnames: new Surnames('Rodriguez'),
            email: new Email('maria@example.com'),
            password: Password::fromPlainText('SecurePass123!'),
            phone: new Phone('+584149876543'),
            walletBalance: new Money(0.00, 'VES'),
            identificationCard: new NationalId('V-18765432'),
            birthDate: new BirthDate('1995-08-20'),
            roles: $roles,
            permissions: $permissions
        );
    }

    public function test_throws_401_when_user_is_not_authenticated(): void
    {
        $middleware = AuthorizeMiddleware::role('client');
        $request = new Request([], [], [], [], [], []);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(401);

        $middleware->handle($request, fn() => true);
    }

    public function test_allows_access_when_user_has_required_role(): void
    {
        $user = $this->createUser(roles: [new Role(1, 'client')]);
        $request = new Request([], [], [], [], [], []);
        $request->setUser($user);

        $middleware = AuthorizeMiddleware::role('client');

        $executedNext = false;
        $middleware->handle($request, function () use (&$executedNext) {
            $executedNext = true;
            return true;
        });

        $this->assertTrue($executedNext);
    }

    public function test_throws_403_when_user_lacks_required_role(): void
    {
        $user = $this->createUser(roles: [new Role(2, 'driver')]);
        $request = new Request([], [], [], [], [], []);
        $request->setUser($user);

        // Intenta acceder a una acción exclusiva de cliente
        $middleware = AuthorizeMiddleware::role('client');

        $this->expectException(Exception::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage('No tienes el rol requerido (client)');

        $middleware->handle($request, fn() => true);
    }

    public function test_allows_access_when_user_has_direct_permission(): void
    {
        $user = $this->createUser(permissions: [new Permission(1, 'request_sos')]);
        $request = new Request([], [], [], [], [], []);
        $request->setUser($user);

        $middleware = AuthorizeMiddleware::permission('request_sos');

        $executedNext = false;
        $middleware->handle($request, function () use (&$executedNext) {
            $executedNext = true;
            return true;
        });

        $this->assertTrue($executedNext);
    }

    public function test_allows_access_when_user_has_permission_inherited_from_role(): void
    {
        $role = new Role(1, 'driver');
        $role->givePermissionTo(new Permission(5, 'accept_sos'));

        $user = $this->createUser(roles: [$role]);
        $request = new Request([], [], [], [], [], []);
        $request->setUser($user);

        $middleware = AuthorizeMiddleware::permission('accept_sos');

        $executedNext = false;
        $middleware->handle($request, function () use (&$executedNext) {
            $executedNext = true;
            return true;
        });

        $this->assertTrue($executedNext);
    }

    public function test_throws_403_when_user_lacks_permission(): void
    {
        $user = $this->createUser(roles: [new Role(1, 'client')]);
        $request = new Request([], [], [], [], [], []);
        $request->setUser($user);

        // Cliente no tiene el permiso accept_sos
        $middleware = AuthorizeMiddleware::permission('accept_sos');

        $this->expectException(Exception::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage('No tienes los permisos necesarios para realizar esta acción');

        $middleware->handle($request, fn() => true);
    }
}
