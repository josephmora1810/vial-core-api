<?php

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\Auth\ValueObjects\Password;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PasswordTest extends TestCase
{
    public function test_can_create_from_plain_text_and_verifies_correctly(): void
    {
        $password = Password::fromPlainText('ClaveValida123!');

        $this->assertNotSame('ClaveValida123!', $password->getValue());
        $this->assertTrue(str_starts_with($password->getValue(), '$2y$')); // Bcrypt prefix
        $this->assertTrue($password->verify('ClaveValida123!'));
        $this->assertFalse($password->verify('ClaveIncorrecta!'));
    }

    public function test_can_create_from_existing_hash(): void
    {
        $rawHash = password_hash('MiClave123!', PASSWORD_BCRYPT);
        $password = Password::fromHash($rawHash);

        $this->assertSame($rawHash, $password->getValue());
        $this->assertTrue($password->verify('MiClave123!'));
    }

    public function test_throws_exception_if_plain_text_is_too_short(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La contraseña debe tener entre 7 y 21 caracteres');

        Password::fromPlainText('Ab1');
    }

    public function test_throws_exception_if_missing_uppercase(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Password::fromPlainText('solominusculay123');
    }

    public function test_throws_exception_if_missing_numbers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Password::fromPlainText('SinNumerosMayus');
    }
}
