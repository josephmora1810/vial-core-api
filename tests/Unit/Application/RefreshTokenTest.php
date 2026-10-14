<?php

namespace Tests\Unit\Application;

use App\Application\UseCases\RefreshToken;
use App\Core\JwtService;
use App\Domain\Entities\PersonalAccessToken;
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
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryPersonalAccessTokenRepository;
use Tests\Doubles\InMemoryUserRepository;

class RefreshTokenTest extends TestCase
{
    private InMemoryUserRepository $userRepository;
    private InMemoryPersonalAccessTokenRepository $tokenRepository;
    private JwtService $jwtService;
    private RefreshToken $useCase;
    private User $activeUser;
    private string $validPlainToken;

    protected function setUp(): void
    {
        $this->userRepository = new InMemoryUserRepository();
        $this->tokenRepository = new InMemoryPersonalAccessTokenRepository($this->userRepository);
        $this->jwtService = new JwtService();
        $this->useCase = new RefreshToken(
            $this->tokenRepository,
            $this->userRepository,
            $this->jwtService
        );

        $this->activeUser = new User(
            id: null,
            uuid: Uuid::generate(),
            names: new Names('LUIS MIGUEL'),
            surnames: new Surnames('GALLEGO BASTERRICA'),
            email: new Email('luismiguel@ubikgo.com'),
            password: Password::fromPlainText('SolDeMexico123!'),
            phone: new Phone('+584145551234'),
            walletBalance: new Money(100.00, 'USD'),
            identificationCard: new NationalId('V20111222'),
            birthDate: new BirthDate('1985-05-15'),
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            consentAt: new DateTimeImmutable(),
            deletedAt: null,
            isActive: true,
            roles: [new Role(1, 'driver', 'api')],
            permissions: []
        );
        $this->userRepository->save($this->activeUser);

        // Crear token inicial para el usuario
        $this->validPlainToken = bin2hex(random_bytes(32));
        $tokenEntity = PersonalAccessToken::create(
            userId: (int)$this->activeUser->getId(),
            plainToken: $this->validPlainToken,
            expiresAt: (new DateTimeImmutable())->modify('+30 days')
        );
        $this->tokenRepository->save($tokenEntity);
    }

    public function test_can_refresh_token_successfully_and_rotates(): void
    {
        $oldTokenHash = hash('sha256', $this->validPlainToken);

        $result = $this->useCase->execute($this->validPlainToken);

        // 1. Debe retornar user DTO, nuevo access token y nuevo refresh token
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertSame('luismiguel@ubikgo.com', $result['user']['email']);
        $this->assertSame('driver', $result['user']['role']);

        // 2. El nuevo refresh token debe ser diferente al original (rotación)
        $this->assertNotSame($this->validPlainToken, $result['refresh_token']);

        // 3. El token antiguo debe quedar revocado
        $oldToken = $this->tokenRepository->findByTokenHash($oldTokenHash);
        $this->assertNotNull($oldToken);
        $this->assertTrue($oldToken->isRevoked());
        $this->assertFalse($oldToken->isValid());

        // 4. El nuevo refresh token debe estar guardado y activo
        $newTokenHash = hash('sha256', $result['refresh_token']);
        $newToken = $this->tokenRepository->findByTokenHash($newTokenHash);
        $this->assertNotNull($newToken);
        $this->assertTrue($newToken->isValid());

        // 5. El nuevo JWT debe ser válido
        $payload = $this->jwtService->validateToken($result['token']);
        $this->assertSame($this->activeUser->getUuid()->getValue(), $payload['sub']);
        $this->assertSame('driver', $payload['role']);
    }

    public function test_fails_when_refresh_token_is_empty(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("El refresh token es requerido.");

        $this->useCase->execute('   ');
    }

    public function test_fails_when_refresh_token_does_not_exist(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("El refresh token es inválido, ha expirado o fue revocado.");

        $this->useCase->execute(bin2hex(random_bytes(32)));
    }

    public function test_fails_when_refresh_token_is_already_revoked(): void
    {
        // Revocamos el token
        $tokenHash = hash('sha256', $this->validPlainToken);
        $this->tokenRepository->revokeByTokenHash($tokenHash);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("El refresh token es inválido, ha expirado o fue revocado.");

        $this->useCase->execute($this->validPlainToken);
    }

    public function test_fails_when_refresh_token_has_expired(): void
    {
        // Creamos un token expirado
        $expiredPlainToken = bin2hex(random_bytes(32));
        $expiredToken = new PersonalAccessToken(
            id: null,
            userId: (int)$this->activeUser->getId(),
            tokenHash: hash('sha256', $expiredPlainToken),
            expiresAt: (new DateTimeImmutable())->modify('-1 hour'),
            isRevoked: false,
            createdAt: (new DateTimeImmutable())->modify('-31 days')
        );
        $this->tokenRepository->save($expiredToken);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("El refresh token es inválido, ha expirado o fue revocado.");

        $this->useCase->execute($expiredPlainToken);
    }

    public function test_fails_when_user_is_deactivated(): void
    {
        // Desactivamos al usuario
        $this->activeUser->deactivate();
        $this->userRepository->save($this->activeUser);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("La cuenta de usuario está inactiva o suspendida.");

        $this->useCase->execute($this->validPlainToken);
    }
}
