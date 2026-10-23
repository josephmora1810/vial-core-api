<?php

namespace Tests\Unit\Domain\Entities;

use App\Domain\Auth\Entities\PersonalAccessToken;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class PersonalAccessTokenTest extends TestCase
{
    public function testCanCreateViaSemanticFactory(): void
    {
        $plainToken = 'secret_session_token_12345';
        $expiresAt = (new DateTimeImmutable())->modify('+30 days');

        $token = PersonalAccessToken::create(
            userId: 7,
            plainToken: $plainToken,
            expiresAt: $expiresAt
        );

        $this->assertNull($token->getId());
        $this->assertSame(7, $token->getUserId());
        $this->assertSame(hash('sha256', $plainToken), $token->getTokenHash());
        $this->assertSame($expiresAt, $token->getExpiresAt());
        $this->assertFalse($token->isRevoked());
        $this->assertInstanceOf(DateTimeImmutable::class, $token->getCreatedAt());
        $this->assertTrue($token->isValid());
    }

    public function testSetIdWorks(): void
    {
        $token = PersonalAccessToken::create(
            userId: 7,
            plainToken: 'token',
            expiresAt: (new DateTimeImmutable())->modify('+1 day')
        );

        $token->setId(105);
        $this->assertSame(105, $token->getId());
    }

    public function testIsValidReturnsFalseWhenRevoked(): void
    {
        $token = PersonalAccessToken::create(
            userId: 7,
            plainToken: 'token',
            expiresAt: (new DateTimeImmutable())->modify('+1 day')
        );

        $this->assertTrue($token->isValid());
        $token->revoke();
        $this->assertTrue($token->isRevoked());
        $this->assertFalse($token->isValid());
    }

    public function testIsValidReturnsFalseWhenExpired(): void
    {
        $pastDate = (new DateTimeImmutable())->modify('-1 hour');
        $token = new PersonalAccessToken(
            id: 1,
            userId: 7,
            tokenHash: hash('sha256', 'expired_token'),
            expiresAt: $pastDate,
            isRevoked: false
        );

        $this->assertFalse($token->isRevoked());
        $this->assertFalse($token->isValid());
    }
}
