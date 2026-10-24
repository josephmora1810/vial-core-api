<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\JwtService;
use Exception;
use PHPUnit\Framework\TestCase;

final class JwtServiceTest extends TestCase
{
    private JwtService $jwtService;

    protected function setUp(): void
    {
        $this->jwtService = new JwtService();
    }

    public function testGenerateAndValidateTokenSuccess(): void
    {
        $payload = ['sub' => 'user-uuid-123', 'role' => 'client'];
        $token = $this->jwtService->generateToken($payload);

        $this->assertIsString($token);
        $this->assertCount(3, explode('.', $token));

        $decoded = $this->jwtService->validateToken($token);
        $this->assertSame('user-uuid-123', $decoded['sub']);
        $this->assertSame('client', $decoded['role']);
        $this->assertArrayHasKey('exp', $decoded);
    }

    public function testValidateTamperedTokenThrowsException(): void
    {
        $token = $this->jwtService->generateToken(['sub' => 'user-1']);
        $parts = explode('.', $token);
        $parts[1] = base64_encode(json_encode(['sub' => 'attacker']));
        $tamperedToken = implode('.', $parts);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(401);

        $this->jwtService->validateToken($tamperedToken);
    }

    public function testValidateInvalidFormatThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(401);

        $this->jwtService->validateToken('invalid.token');
    }
}
