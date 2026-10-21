<?php

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\Vehicles\ValueObjects\EngineSerial;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EngineSerialTest extends TestCase
{
    public function test_can_create_valid_engine_serial(): void
    {
        $serial = new EngineSerial('4Y-12345678');

        $this->assertSame('4Y12345678', $serial->getValue());
        $this->assertSame('4Y12345678', (string)$serial);
    }

    public function test_equality(): void
    {
        $s1 = new EngineSerial('4Y12345678');
        $s2 = new EngineSerial('4y-12345678');

        $this->assertTrue($s1->equals($s2));
    }

    public function test_throws_exception_if_too_short(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El serial de motor "123" no es válido.');

        new EngineSerial('123');
    }

    public function test_throws_exception_on_invalid_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EngineSerial('SERIAL#123@');
    }
}
