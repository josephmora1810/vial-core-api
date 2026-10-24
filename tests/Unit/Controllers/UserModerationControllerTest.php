<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Application\Users\DTOs\{
    UserAvatarResponseDto,
    UserModerationResponseDto
};
use App\Application\Users\UseCases\{
    ActivateUser,
    SuspendUser,
    UploadUserAvatar
};
use App\Controllers\UserModerationController;
use App\Core\{Request, Response};
use App\Domain\Auth\{Entities\Role, ValueObjects\Password};
use App\Domain\Shared\ValueObjects\{Money, Uuid};
use App\Domain\Users\Entities\User;
use App\Domain\Users\ValueObjects\{
    BirthDate,
    Email,
    Names,
    NationalId,
    Phone,
    Surnames
};
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;

final class UserModerationControllerTest extends TestCase
{
    private SuspendUser $suspendUser;
    private ActivateUser $activateUser;
    private UploadUserAvatar $uploadUserAvatar;
    private UserModerationController $controller;

    protected function setUp(): void
    {
        Response::$shouldExit = false;
        $_POST = [];
        $_GET = [];
        $_FILES = [];

        $this->suspendUser = $this->createStub(SuspendUser::class);
        $this->activateUser = $this->createStub(ActivateUser::class);
        $this->uploadUserAvatar = $this->createStub(UploadUserAvatar::class);

        $this->controller = new UserModerationController(
            suspendUser: $this->suspendUser,
            activateUser: $this->activateUser,
            uploadUserAvatar: $this->uploadUserAvatar
        );
    }

    protected function tearDown(): void
    {
        Response::$shouldExit = true;
        $_POST = [];
        $_GET = [];
        $_FILES = [];
    }

    private function createAdminUser(): User
    {
        return new User(
            id: 1,
            uuid: Uuid::generate(),
            names: new Names('Admin'),
            surnames: new Surnames('Principal'),
            email: new Email('admin@example.com'),
            password: Password::fromPlainText('AdminPass123!'),
            phone: new Phone('+584120001122'),
            walletBalance: new Money(0, 'VES'),
            identificationCard: new NationalId('V-10000001'),
            birthDate: new BirthDate('1980-01-01'),
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            consentAt: new DateTimeImmutable(),
            deletedAt: null,
            isActive: true,
            roles: [new Role(3, 'admin')]
        );
    }

    private function capture(callable $callback): array
    {
        ob_start();
        try {
            $callback();
        } finally {
            $output = (string)ob_get_clean();
        }
        return json_decode($output, true) ?: [];
    }

    public function testSuspendMissingReasonReturns422(): void
    {
        $request = new Request();
        $request->setUser($this->createAdminUser());
        $_POST = [];

        $res = $this->capture(fn() => $this->controller->suspend($request, 5));
        $this->assertSame(422, $res['code']);
        $this->assertArrayHasKey('reason', $res['data']);
    }

    public function testSuspendSuccess(): void
    {
        $request = new Request();
        $request->setUser($this->createAdminUser());
        $_POST = ['reason' => 'Comportamiento sospechoso'];

        $this->suspendUser->method('execute')->willReturn([
            'user_id' => 5,
            'status'  => 'suspended',
            'reason'  => 'Comportamiento sospechoso',
        ]);

        $res = $this->capture(fn() => $this->controller->suspend($request, 5));
        $this->assertSame(200, $res['code']);
        $this->assertSame(5, $res['data']['user_id']);
        $this->assertSame('suspended', $res['data']['status']);
    }

    public function testActivateSuccess(): void
    {
        $request = new Request();
        $request->setUser($this->createAdminUser());

        $this->activateUser->method('execute')->willReturn([
            'user_id' => 5,
            'status'  => 'active',
        ]);

        $res = $this->capture(fn() => $this->controller->activate($request, 5));
        $this->assertSame(200, $res['code']);
        $this->assertSame('active', $res['data']['status']);
    }

    public function testUploadAvatarUnauthorized(): void
    {
        $res = $this->capture(fn() => $this->controller->uploadAvatar(new Request()));
        $this->assertSame(401, $res['code']);
    }

    public function testUploadAvatarMissingFileReturns422(): void
    {
        $request = new Request();
        $request->setUser($this->createAdminUser());
        $_FILES = [];

        $res = $this->capture(fn() => $this->controller->uploadAvatar($request));
        $this->assertSame(422, $res['code']);
        $this->assertArrayHasKey('avatar', $res['data']);
    }

    public function testUploadAvatarInvalidExtensionReturns422(): void
    {
        $request = new Request();
        $request->setUser($this->createAdminUser());
        $_FILES['avatar'] = [
            'name'     => 'test.exe',
            'type'     => 'application/octet-stream',
            'tmp_name' => __DIR__ . '/test.exe',
            'error'    => 0,
            'size'     => 100,
        ];

        $res = $this->capture(fn() => $this->controller->uploadAvatar($request));
        $this->assertSame(422, $res['code']);
    }
}
