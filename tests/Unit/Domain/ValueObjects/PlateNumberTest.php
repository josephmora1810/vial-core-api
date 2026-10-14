<?php

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\ValueObjects\PlateNumber;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PlateNumberTest extends TestCase
{
    public function test_can_create_valid_venezuelan_plate_number(): void
    {
        $plate = new PlateNumber('AB123CD');

        $this->assertSame('AB123CD', $plate->getValue());
        $this->assertSame('AB123CD', (string)$plate);
    }

    public function test_normalizes_lowercase_spaces_and_hyphens(): void
    {
        $plate = new PlateNumber('ab-123 cd');

        $this->assertSame('AB123CD', $plate->getValue());
    }

    public function test_equality(): void
    {
        $p1 = new PlateNumber('AB123CD');
        $p2 = new PlateNumber('ab123cd');

        $this->assertTrue($p1->equals($p2));
    }

    public function test_throws_exception_on_invalid_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La placa "123ABCD" no es válida.');

        new PlateNumber('123ABCD');
    }
}
