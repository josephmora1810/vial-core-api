<?php

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\Users\ValueObjects\NationalId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class NationalIdTest extends TestCase
{
    public function test_can_create_valid_venezuelan_national_id(): void
    {
        $id = new NationalId('V-12345678');

        $this->assertSame('V-12345678', $id->getValue());
        $this->assertSame('V', $id->getNationality());
        $this->assertSame(12345678, $id->getNumber());
        $this->assertTrue($id->isVenezuelan());
        $this->assertFalse($id->isForeigner());
        $this->assertSame('V-12345678', (string)$id);
    }

    public function test_normalizes_unhyphenated_input(): void
    {
        $id = new NationalId('v80123456');

        $this->assertSame('V-80123456', $id->getValue());
        $this->assertSame(80123456, $id->getNumber());
    }

    public function test_supports_foreign_national_id(): void
    {
        $id = new NationalId('E-84555666');

        $this->assertSame('E-84555666', $id->getValue());
        $this->assertTrue($id->isForeigner());
        $this->assertFalse($id->isVenezuelan());
    }

    public function test_can_create_from_parts(): void
    {
        $id = NationalId::fromParts('v', 12345678);

        $this->assertSame('V-12345678', $id->getValue());
    }

    public function test_throws_exception_on_invalid_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La cédula "J-12345678" no es válida.');

        new NationalId('J-12345678');
    }

    public function test_throws_exception_when_digits_are_too_few(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NationalId('V-123');
    }
}
