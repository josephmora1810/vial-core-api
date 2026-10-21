<?php

namespace Tests\Unit\Application;

use App\Application\Auth\UseCases\LogoutUser;
use App\Domain\Auth\Entities\PersonalAccessToken;
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryPersonalAccessTokenRepository;

class LogoutUserTest extends TestCase
{
    private InMemoryPersonalAccessTokenRepository $tokenRepository;
    private LogoutUser $useCase;
    private string $validPlainToken;

    protected function setUp(): void
    {
        $this->tokenRepository = new InMemoryPersonalAccessTokenRepository();
        $this->useCase = new LogoutUser($this->tokenRepository);

        $this->validPlainToken = bin2hex(random_bytes(32));
        $tokenEntity = PersonalAccessToken::create(
            userId: 1,
            plainToken: $this->validPlainToken,
            expiresAt: (new DateTimeImmutable())->modify('+30 days')
        );
        $this->tokenRepository->save($tokenEntity);
    }

    public function test_logout_revokes_token_successfully(): void
    {
        $tokenHash = hash('sha256', $this->validPlainToken);
        $tokenBefore = $this->tokenRepository->findByTokenHash($tokenHash);
        $this->assertTrue($tokenBefore->isValid());

        $this->useCase->execute($this->validPlainToken);

        $tokenAfter = $this->tokenRepository->findByTokenHash($tokenHash);
        $this->assertNotNull($tokenAfter);
        $this->assertTrue($tokenAfter->isRevoked());
        $this->assertFalse($tokenAfter->isValid());
    }

    public function test_logout_fails_when_token_is_empty(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("El refresh token es requerido para cerrar sesión.");

        $this->useCase->execute('   ');
    }
}
