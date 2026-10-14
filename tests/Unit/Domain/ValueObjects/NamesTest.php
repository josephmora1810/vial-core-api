<?php

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\ValueObjects\Names;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class NamesTest extends TestCase
{
    public function test_can_create_valid_names(): void
    {
        $names = new Names('  carlos   alberto  ');

        $this->assertSame('carlos alberto', $names->getValue());
        $this->assertSame('carlos', $names->getFirstName());
        $this->assertSame('alberto', $names->getMiddleNames());
        $this->assertSame(2, $names->count());
        $this->assertSame(['carlos', 'alberto'], $names->all());
    }

    public function test_supports_accents_and_single_name(): void
    {
        $names = new Names('José');

        $this->assertSame('José', $names->getValue());
        $this->assertSame('José', $names->getFirstName());
        $this->assertNull($names->getMiddleNames());
        $this->assertSame(1, $names->count());
    }

    public function test_equality_is_case_insensitive(): void
    {
        $names1 = new Names('ANDREÍNA SOFÍA');
        $names2 = new Names('andreína sofía');

        $this->assertTrue($names1->equals($names2));
    }

    public function test_throws_exception_if_too_short(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El campo de nombres debe tener al menos 2 caracteres.');

        new Names('A');
    }

    public function test_throws_exception_if_contains_numbers_or_symbols(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Names('Carlos 123');
    }
}
