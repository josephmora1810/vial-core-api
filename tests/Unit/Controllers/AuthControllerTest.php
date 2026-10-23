<?php

namespace Tests\Unit\Controllers;

use App\Application\Auth\UseCases\{
    LoginUser,
    LogoutUser,
    RefreshToken,
    RegisterUser,
    ResetPassword,
    ScanIdentityCard,
    SendPasswordResetCode
};
use App\Controllers\AuthController;
use App\Core\{Request, Response};
use App\Domain\Auth\Entities\Role;
use App\Domain\Shared\Repositories\ImageRepositoryInterface;
use App\Domain\Shared\ValueObjects\{Money, Uuid};
use App\Domain\Users\Entities\User;
use App\Domain\Users\ValueObjects\{BirthDate, Email, Names, NationalId, Password, Phone, Surnames};
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;

class AuthControllerTest extends TestCase
{
    private LoginUser $loginUseCase;
    private RegisterUser $registerUseCase;
    private RefreshToken $refreshTokenUseCase;
    private LogoutUser $logoutUseCase;
    private SendPasswordResetCode $sendPasswordResetCode;
    private ResetPassword $resetPassword;
    private ScanIdentityCard $scanIdentityCard;
    private ImageRepositoryInterface $imageRepository;
    private AuthController $controller;

    protected function setUp(): void
    {
        Response::$shouldExit = false;
        $_POST = [];
        $_FILES = [];

        $this->loginUseCase = $this->createStub(LoginUser::class);
        $this->registerUseCase = $this->createStub(RegisterUser::class);
        $this->refreshTokenUseCase = $this->createStub(RefreshToken::class);
        $this->logoutUseCase = $this->createStub(LogoutUser::class);
        $this->sendPasswordResetCode = $this->createStub(SendPasswordResetCode::class);
        $this->resetPassword = $this->createStub(ResetPassword::class);
        $this->scanIdentityCard = $this->createStub(ScanIdentityCard::class);
        $this->imageRepository = $this->createStub(ImageRepositoryInterface::class);

        $this->controller = new AuthController(
            $this->loginUseCase,
            $this->registerUseCase,
            $this->refreshTokenUseCase,
            $this->logoutUseCase,
            $this->sendPasswordResetCode,
            $this->resetPassword,
            $this->scanIdentityCard,
            $this->imageRepository
        );
    }

    protected function tearDown(): void
    {
        Response::$shouldExit = true;
        $_POST = [];
        $_FILES = [];
    }

    private function createSampleUser(): User
    {
        return new User(
            id: 1,
            uuid: new Uuid('018e3d2f-1234-7123-8123-0123456789ab'),
            names: new Names('Jose Alberto'),
            surnames: new Surnames('Mora Perez'),
            email: new Email('jose@example.com'),
            password: \App\Domain\Auth\ValueObjects\Password::fromPlainText('ClaveSegura123!'),
            phone: new Phone('+584121234567'),
            walletBalance: new Money(0.0, 'VES'),
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

    public function testLoginFailsWhenEmailOrPasswordMissing(): void
    {
        $_POST = ['email' => ''];
        $request = new Request();
        ob_start();
        $this->controller->login($request);
        $output = ob_get_clean();

        $this->assertSame(400, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('fail', $data['status']);
        $this->assertStringContainsString('requeridos', $data['message']);
    }

    public function testLoginSucceeds(): void
    {
        $_POST = ['email' => 'test@example.com', 'password' => 'Password123!'];
        $request = new Request();

        $this->loginUseCase->method('execute')
            ->willReturn([
                'user'          => ['id' => 1, 'email' => 'test@example.com'],
                'token'         => 'mock_jwt_token',
                'refresh_token' => 'mock_refresh_token',
            ]);

        ob_start();
        $this->controller->login($request);
        $output = ob_get_clean();

        $this->assertSame(200, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('success', $data['status']);
        $this->assertSame('mock_jwt_token', $data['data']['token']);
    }

    public function testLoginFailsWithInvalidCredentials(): void
    {
        $_POST = ['email' => 'test@example.com', 'password' => 'WrongPass'];
        $request = new Request();

        $this->loginUseCase->method('execute')
            ->willThrowException(new Exception('Credenciales incorrectas.', 401));

        ob_start();
        $this->controller->login($request);
        $output = ob_get_clean();

        $this->assertSame(401, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('fail', $data['status']);
    }

    public function testScanIdentityFailsWhenNoFileOrPathProvided(): void
    {
        $_POST = [];
        $request = new Request();
        ob_start();
        $this->controller->scanIdentity($request);
        $output = ob_get_clean();

        $this->assertSame(400, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('fail', $data['status']);
    }

    public function testScanIdentitySucceeds(): void
    {
        $filePath = '/tmp/cedula.jpg';
        $_POST = ['file_path' => $filePath];
        $request = new Request();

        $this->scanIdentityCard->method('execute')
            ->willReturn([
                'suggested_identity' => ['identification_card' => 'V-12345678'],
                'file_path'          => $filePath,
            ]);

        ob_start();
        $this->controller->scanIdentity($request);
        $output = ob_get_clean();

        $this->assertSame(200, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('success', $data['status']);
        $this->assertSame('V-12345678', $data['data']['suggested_identity']['identification_card']);
    }

    public function testRegisterFailsWhenRequiredFieldsMissing(): void
    {
        $_POST = ['email' => 'test@example.com'];
        $request = new Request();
        ob_start();
        $this->controller->register($request);
        $output = ob_get_clean();

        $this->assertSame(400, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('fail', $data['status']);
        $this->assertStringContainsString('son requeridos', $data['message']);
    }

    public function testRegisterFailsWhenNoDocumentOrPatronymics(): void
    {
        $_POST = [
            'email'    => 'test@example.com',
            'password' => 'Password123!',
            'phone'    => '+584121234567',
        ];
        $request = new Request();

        ob_start();
        $this->controller->register($request);
        $output = ob_get_clean();

        $this->assertSame(400, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('fail', $data['status']);
    }

    public function testRegisterSucceeds(): void
    {
        $_POST = [
            'email'               => 'test@example.com',
            'password'            => 'Password123!',
            'phone'               => '+584121234567',
            'role'                => 'client',
            'terms_accepted'      => true,
            'identification_card' => 'V-12345678',
            'names'               => 'Jose Alberto',
            'surnames'            => 'Mora Perez',
            'birth_date'          => '1990-01-01',
        ];
        $request = new Request();

        $this->registerUseCase->method('execute')
            ->willReturn([
                'user'          => ['id' => 1, 'email' => 'test@example.com'],
                'token'         => 'jwt_token',
                'refresh_token' => 'refresh_token',
            ]);

        ob_start();
        $this->controller->register($request);
        $output = ob_get_clean();

        $this->assertSame(201, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('success', $data['status']);
    }

    public function testRefreshTokenFailsWhenTokenMissing(): void
    {
        $_POST = [];
        $request = new Request();
        ob_start();
        $this->controller->refreshToken($request);
        $output = ob_get_clean();

        $this->assertSame(400, http_response_code());
    }

    public function testRefreshTokenSucceeds(): void
    {
        $_POST = ['refresh_token' => 'plain_refresh_token'];
        $request = new Request();

        $this->refreshTokenUseCase->method('execute')
            ->willReturn([
                'user'          => ['id' => 1],
                'token'         => 'new_jwt',
                'refresh_token' => 'new_refresh_token',
            ]);

        ob_start();
        $this->controller->refreshToken($request);
        $output = ob_get_clean();

        $this->assertSame(200, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('success', $data['status']);
        $this->assertSame('new_jwt', $data['data']['token']);
    }

    public function testLogoutFailsWhenTokenMissing(): void
    {
        $_POST = [];
        $request = new Request();
        ob_start();
        $this->controller->logout($request);
        $output = ob_get_clean();

        $this->assertSame(400, http_response_code());
    }

    public function testLogoutSucceeds(): void
    {
        $_POST = ['refresh_token' => 'token_to_revoke'];
        $request = new Request();

        ob_start();
        $this->controller->logout($request);
        $output = ob_get_clean();

        $this->assertSame(200, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('success', $data['status']);
    }

    public function testMeFailsWhenUnauthenticated(): void
    {
        $_POST = [];
        $request = new Request();
        ob_start();
        $this->controller->me($request);
        $output = ob_get_clean();

        $this->assertSame(401, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('fail', $data['status']);
    }

    public function testMeSucceedsWhenAuthenticated(): void
    {
        $user = $this->createSampleUser();
        $_POST = [];
        $request = new Request();
        $request->setUser($user);

        $this->imageRepository->method('findLatestByImageable')
            ->willReturn(['path' => 'https://s3.amazonaws.com/bucket/avatar.jpg']);

        ob_start();
        $this->controller->me($request);
        $output = ob_get_clean();

        $this->assertSame(200, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('success', $data['status']);
        $this->assertSame('jose@example.com', $data['data']['email']);
        $this->assertSame('https://s3.amazonaws.com/bucket/avatar.jpg', $data['data']['avatar_url']);
        $this->assertSame('JM', $data['data']['initials']);
    }

    public function testForgotPasswordFailsWhenEmailMissing(): void
    {
        $_POST = [];
        $request = new Request();
        ob_start();
        $this->controller->forgotPassword($request);
        $output = ob_get_clean();

        $this->assertSame(400, http_response_code());
    }

    public function testForgotPasswordSucceeds(): void
    {
        $_POST = ['email' => 'client@ubikgo.com'];
        $request = new Request();

        ob_start();
        $this->controller->forgotPassword($request);
        $output = ob_get_clean();

        $this->assertSame(200, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('success', $data['status']);
    }

    public function testResetPasswordFailsWhenParamsMissing(): void
    {
        $_POST = ['email' => 'client@ubikgo.com'];
        $request = new Request();
        ob_start();
        $this->controller->resetPassword($request);
        $output = ob_get_clean();

        $this->assertSame(400, http_response_code());
    }

    public function testResetPasswordSucceeds(): void
    {
        $_POST = [
            'email'                 => 'client@ubikgo.com',
            'code'                  => '123456',
            'password'              => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ];
        $request = new Request();

        ob_start();
        $this->controller->resetPassword($request);
        $output = ob_get_clean();

        $this->assertSame(200, http_response_code());
        $data = json_decode($output, true);
        $this->assertSame('success', $data['status']);
    }
}
