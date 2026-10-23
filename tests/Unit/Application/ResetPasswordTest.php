<?php

namespace Tests\Unit\Application;

use App\Application\Auth\UseCases\ResetPassword;
use App\Domain\Auth\{
    Entities\PersonalAccessToken,
    Entities\Role,
    ValueObjects\Password
};
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
use App\Infrastructure\Database\Redis;
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\{InMemoryPersonalAccessTokenRepository, InMemoryUserRepository};

class ResetPasswordTest extends TestCase
{
    private InMemoryUserRepository $userRepository;
    private InMemoryPersonalAccessTokenRepository $tokenRepository;
    private Redis $redis;
    private ResetPassword $useCase;
    private User $user;
    private string $redisKey;
    private string $plainToken;

    protected function setUp(): void
    {
        $this->userRepository = new InMemoryUserRepository();
        $this->tokenRepository = new InMemoryPersonalAccessTokenRepository($this->userRepository);
        $this->redis = new Redis();
        $this->useCase = new ResetPassword(
            $this->userRepository,
            $this->tokenRepository,
            $this->redis
        );

        $this->user = new User(
            id: null,
            uuid: Uuid::generate(),
            names: new Names('ROBERTO JOSÉ'),
            surnames: new Surnames('GÓMEZ BOLAÑOS'),
            email: new Email('roberto@ubikgo.com'),
            password: Password::fromPlainText('ViejaClave123!'),
            phone: new Phone('+584149998877'),
            walletBalance: new Money(0.00, 'VES'),
            identificationCard: new NationalId('V14555666'),
            birthDate: new BirthDate('1980-02-21'),
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            consentAt: new DateTimeImmutable(),
            deletedAt: null,
            isActive: true,
            roles: [new Role(1, 'client', 'api')],
            permissions: []
        );

        $this->userRepository->save($this->user);

        // Crear una sesión activa previa para verificar revocación
        $this->plainToken = bin2hex(random_bytes(32));
        $tokenEntity = PersonalAccessToken::create(
            userId: (int)$this->user->getId(),
            plainToken: $this->plainToken,
            expiresAt: (new DateTimeImmutable())->modify('+30 days')
        );
        $this->tokenRepository->save($tokenEntity);

        $this->redisKey = 'password_reset:' . md5('roberto@ubikgo.com');
        $this->redis->execute('SET', $this->redisKey, '123456');
    }

    protected function tearDown(): void
    {
        $this->redis->execute('DEL', $this->redisKey);
    }

    public function test_can_reset_password_successfully(): void
    {
        $tokenHash = hash('sha256', $this->plainToken);
        $tokenBefore = $this->tokenRepository->findByTokenHash($tokenHash);
        $this->assertTrue($tokenBefore->isValid());

        $this->useCase->execute(
            rawEmail: 'roberto@ubikgo.com',
            rawCode: '123456',
            newPassword: 'NuevaClaveSuperSegura123!',
            newPasswordConfirmation: 'NuevaClaveSuperSegura123!'
        );

        // 1. La contraseña del usuario debe haber cambiado y ser válida con Bcrypt
        $updatedUser = $this->userRepository->findByEmail(new Email('roberto@ubikgo.com'));
        $this->assertNotNull($updatedUser);
        $this->assertTrue($updatedUser->getPassword()->verify('NuevaClaveSuperSegura123!'));
        $this->assertFalse($updatedUser->getPassword()->verify('ViejaClave123!'));

        // 2. Las sesiones anteriores deben haber sido revocadas
        $tokenAfter = $this->tokenRepository->findByTokenHash($tokenHash);
        $this->assertNotNull($tokenAfter);
        $this->assertTrue($tokenAfter->isRevoked());

        // 3. El código OTP en Redis debe haber sido eliminado
        $storedCode = $this->redis->execute('GET', $this->redisKey);
        $this->assertNull($storedCode);
    }

    public function test_fails_when_passwords_do_not_match(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Las contraseñas no coinciden.");

        $this->useCase->execute(
            rawEmail: 'roberto@ubikgo.com',
            rawCode: '123456',
            newPassword: 'Password123!',
            newPasswordConfirmation: 'OtraPassword999!'
        );
    }

    public function test_fails_with_invalid_or_expired_code(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("El código de recuperación es inválido o ha expirado.");

        $this->useCase->execute(
            rawEmail: 'roberto@ubikgo.com',
            rawCode: '999999', // Código erróneo
            newPassword: 'NuevaPassword123!',
            newPasswordConfirmation: 'NuevaPassword123!'
        );
    }

    public function test_fails_with_empty_code(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("El código de verificación es requerido.");

        $this->useCase->execute(
            rawEmail: 'roberto@ubikgo.com',
            rawCode: '   ',
            newPassword: 'NuevaPassword123!',
            newPasswordConfirmation: 'NuevaPassword123!'
        );
    }

    public function test_brute_force_invalidates_code_after_5_attempts(): void
    {
        $attemptsKey = 'password_reset_attempts:' . md5('roberto@ubikgo.com');
        $this->redis->execute('DEL', $attemptsKey);

        // Primeros 4 intentos fallidos lanzan excepción 400
        for ($i = 1; $i <= 4; $i++) {
            try {
                $this->useCase->execute(
                    rawEmail: 'roberto@ubikgo.com',
                    rawCode: '00000' . $i,
                    newPassword: 'Password123!',
                    newPasswordConfirmation: 'Password123!'
                );
                $this->fail("Debió fallar en el intento {$i}");
            } catch (Exception $e) {
                $this->assertSame(400, $e->getCode());
            }
        }

        // El 5to intento fallido debe lanzar 429 y destruir el código de Redis
        try {
            $this->useCase->execute(
                rawEmail: 'roberto@ubikgo.com',
                rawCode: '000005',
                newPassword: 'Password123!',
                newPasswordConfirmation: 'Password123!'
            );
            $this->fail("Debió fallar en el 5to intento");
        } catch (Exception $e) {
            $this->assertSame(429, $e->getCode());
            $this->assertStringContainsString("Ha superado el límite de intentos fallidos", $e->getMessage());
        }

        // El código en Redis debe haber sido destruido por completo
        $this->assertNull($this->redis->execute('GET', $this->redisKey));
    }
}
