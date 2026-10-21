<?php

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\Users\ValueObjects\Passport;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PassportTest extends TestCase
{
    public function test_can_create_valid_passport(): void
    {
        $passport = new Passport('A1234567');

        $this->assertSame('A1234567', $passport->getValue());
        $this->assertSame('A1234567', (string)$passport);
    }

    public function test_normalizes_spaces_and_hyphens(): void
    {
        $passport = new Passport('a-12 34567');

        $this->assertSame('A1234567', $passport->getValue());
    }

    public function test_throws_exception_if_too_short(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El pasaporte "A12" no es válido.');

        new Passport('A12');
    }
}
