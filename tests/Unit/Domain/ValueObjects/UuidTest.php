<?php

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\Shared\ValueObjects\Uuid;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UuidTest extends TestCase
{
    public function test_can_generate_valid_uuid_v7(): void
    {
        $uuid = Uuid::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid->getValue()
        );
    }

    public function test_can_create_from_valid_uuid_v7_string(): void
    {
        $generated = Uuid::generate()->getValue();
        $uuid = new Uuid($generated);

        $this->assertSame($generated, $uuid->getValue());
        $this->assertSame($generated, (string)$uuid);
    }

    public function test_equality(): void
    {
        $str = Uuid::generate()->getValue();
        $u1 = new Uuid($str);
        $u2 = new Uuid($str);
        $u3 = Uuid::generate();

        $this->assertTrue($u1->equals($u2));
        $this->assertFalse($u1->equals($u3));
    }

    public function test_throws_exception_on_invalid_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El UUID "uuid-invalido-123" no es válido.');

        new Uuid('uuid-invalido-123');
    }
}
