<?php

namespace Tests\Unit\Application;

use App\Application\Auth\UseCases\LoginUser;
use App\Core\JwtService;
use App\Domain\Auth\Entities\Role;
use App\Domain\Users\Entities\User;
use App\Domain\Users\ValueObjects\BirthDate;
use App\Domain\Users\ValueObjects\Email;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Users\ValueObjects\Names;
use App\Domain\Users\ValueObjects\NationalId;
use App\Domain\Auth\ValueObjects\Password;
use App\Domain\Users\ValueObjects\Phone;
use App\Domain\Users\ValueObjects\Surnames;
use App\Domain\Shared\ValueObjects\Uuid;
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryAccountRateLimiter;
use Tests\Doubles\InMemoryPersonalAccessTokenRepository;
use Tests\Doubles\InMemoryUserRepository;

class LoginUserTest extends TestCase
{
    private InMemoryUserRepository $userRepository;
    private InMemoryPersonalAccessTokenRepository $tokenRepository;
    private InMemoryAccountRateLimiter $rateLimiter;
    private JwtService $jwtService;
    private LoginUser $useCase;

    protected function setUp(): void
    {
        $this->userRepository = new InMemoryUserRepository();
        $this->tokenRepository = new InMemoryPersonalAccessTokenRepository($this->userRepository);
        $this->rateLimiter = new InMemoryAccountRateLimiter(maxAttempts: 5);
        $this->jwtService = new JwtService();
        $this->useCase = new LoginUser(
            $this->userRepository,
            $this->tokenRepository,
            $this->jwtService,
            $this->rateLimiter
        );

        // Pre-sembrar un usuario activo para pruebas
        $user = new User(
            id: null,
            uuid: Uuid::generate(),
            names: new Names('CARLOS ALBERTO'),
            surnames: new Surnames('MENDOZA PÉREZ'),
            email: new Email('carlos@ubikgo.com'),
            password: Password::fromPlainText('MiClave123!'),
            phone: new Phone('+584141234567'),
            walletBalance: new Money(0.00, 'VES'),
            identificationCard: new NationalId('V12345678'),
            birthDate: new BirthDate('1988-10-20'),
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            consentAt: new DateTimeImmutable(),
            deletedAt: null,
            isActive: true,
            roles: [new Role(1, 'client', 'api')],
            permissions: []
        );

        $this->userRepository->save($user);
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $result = $this->useCase->execute('carlos@ubikgo.com', 'MiClave123!');

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertSame('carlos@ubikgo.com', $result['user']['email']);
        $this->assertSame('client', $result['user']['role']);
        $this->assertNotEmpty($result['refresh_token']);

        // Validar que el token sea legítimo
        $payload = $this->jwtService->validateToken($result['token']);
        $this->assertSame('carlos@ubikgo.com', $payload['email']);
        $this->assertSame('client', $payload['role']);

        // Validar que el refresh token fue persistido y está activo
        $tokenHash = hash('sha256', $result['refresh_token']);
        $savedToken = $this->tokenRepository->findByTokenHash($tokenHash);
        $this->assertNotNull($savedToken);
        $this->assertTrue($savedToken->isValid());
    }

    public function test_login_fails_with_wrong_password_and_increments_attempts(): void
    {
        $this->assertSame(0, $this->rateLimiter->getAttempts('carlos@ubikgo.com'));

        try {
            $this->useCase->execute('carlos@ubikgo.com', 'PasswordIncorrecta999!');
            $this->fail("Debió lanzar excepción de credenciales incorrectas.");
        } catch (Exception $e) {
            $this->assertSame("Credenciales incorrectas.", $e->getMessage());
            $this->assertSame(401, $e->getCode());
        }

        $this->assertSame(1, $this->rateLimiter->getAttempts('carlos@ubikgo.com'));
    }

    public function test_account_is_locked_after_5_failed_attempts(): void
    {
        // 5 intentos fallidos
        for ($i = 1; $i <= 5; $i++) {
            try {
                $this->useCase->execute('carlos@ubikgo.com', 'ClaveMala' . $i);
            } catch (Exception $e) {
                $this->assertSame("Credenciales incorrectas.", $e->getMessage());
            }
        }

        $this->assertSame(5, $this->rateLimiter->getAttempts('carlos@ubikgo.com'));
        $this->assertTrue($this->rateLimiter->isLocked('carlos@ubikgo.com'));

        // El 6to intento debe ser bloqueado directamente por fuerza bruta (429)
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cuenta bloqueada temporalmente tras 5 intentos fallidos. Intente más tarde.");
        $this->expectExceptionCode(429);

        // Incluso si ahora introduce la clave correcta, no debe permitir el acceso
        $this->useCase->execute('carlos@ubikgo.com', 'MiClave123!');
    }

    public function test_successful_login_resets_failed_attempts(): void
    {
        // 2 intentos fallidos
        try {
            $this->useCase->execute('carlos@ubikgo.com', 'ClaveMala1');
        } catch (Exception) {}
        try {
            $this->useCase->execute('carlos@ubikgo.com', 'ClaveMala2');
        } catch (Exception) {}

        $this->assertSame(2, $this->rateLimiter->getAttempts('carlos@ubikgo.com'));

        // Login exitoso
        $result = $this->useCase->execute('carlos@ubikgo.com', 'MiClave123!');
        $this->assertArrayHasKey('token', $result);

        // El contador debe haberse limpiado
        $this->assertSame(0, $this->rateLimiter->getAttempts('carlos@ubikgo.com'));
        $this->assertFalse($this->rateLimiter->isLocked('carlos@ubikgo.com'));
    }

    public function test_login_fails_when_user_does_not_exist(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Credenciales incorrectas.");

        $this->useCase->execute('fantasma@ubikgo.com', 'CualquierClave123!');
    }

    public function test_login_fails_when_user_is_deactivated(): void
    {
        // Desactivamos al usuario
        $user = $this->userRepository->findByEmail(new Email('carlos@ubikgo.com'));
        $user->deactivate();
        $this->userRepository->save($user);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Tu cuenta ha sido suspendida. Contacta a soporte.");

        $this->useCase->execute('carlos@ubikgo.com', 'MiClave123!');
    }

    public function test_login_fails_with_invalid_email_format(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("El formato del correo electrónico no es válido.");

        $this->useCase->execute('correo_sin_arroba', 'MiClave123!');
    }
}
