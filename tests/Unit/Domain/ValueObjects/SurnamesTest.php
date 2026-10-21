<?php

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\Users\ValueObjects\Surnames;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SurnamesTest extends TestCase
{
    public function test_can_create_valid_surnames(): void
    {
        $surnames = new Surnames('  mendoza   pérez  ');

        $this->assertSame('mendoza pérez', $surnames->getValue());
        $this->assertSame('mendoza', $surnames->getFirstLastName());
        $this->assertSame('pérez', $surnames->getSecondLastName());
        $this->assertSame(2, $surnames->count());
        $this->assertSame(['mendoza', 'pérez'], $surnames->all());
    }

    public function test_supports_single_surname(): void
    {
        $surnames = new Surnames('González');

        $this->assertSame('González', $surnames->getValue());
        $this->assertSame('González', $surnames->getFirstLastName());
        $this->assertNull($surnames->getSecondLastName());
        $this->assertSame(1, $surnames->count());
    }

    public function test_equality_is_case_insensitive(): void
    {
        $surnames1 = new Surnames('QUINTERO BASTIDAS');
        $surnames2 = new Surnames('quintero bastidas');

        $this->assertTrue($surnames1->equals($surnames2));
    }

    public function test_throws_exception_if_too_short(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El campo de apellidos debe tener al menos 2 caracteres.');

        new Surnames('M');
    }

    public function test_throws_exception_if_contains_numbers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Surnames('Mendoza 456');
    }
}
