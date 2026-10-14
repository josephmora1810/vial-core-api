<?php

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\ValueObjects\Phone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    public function test_can_create_valid_venezuelan_phone(): void
    {
        $phone = new Phone('+584121234567');

        $this->assertSame('+584121234567', $phone->getValue());
        $this->assertTrue($phone->isVenezuelan());
        $this->assertSame('412', $phone->getAreaCode());
        $this->assertSame('4121234567', $phone->getSubscriberNumber());
    }

    public function test_normalizes_various_venezuelan_formats(): void
    {
        $this->assertSame('+584149876543', (new Phone('04149876543'))->getValue());
        $this->assertSame('+584149876543', (new Phone('4149876543'))->getValue());
        $this->assertSame('+584149876543', (new Phone('584149876543'))->getValue());
        $this->assertSame('+584149876543', (new Phone('+58-414-987-6543'))->getValue());
    }

    public function test_supports_international_phones(): void
    {
        $phone = new Phone('+12025550123');

        $this->assertSame('+12025550123', $phone->getValue());
        $this->assertFalse($phone->isVenezuelan());
        $this->assertNull($phone->getAreaCode());
    }

    public function test_throws_exception_on_invalid_phone(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Phone('123abc456');
    }

    public function test_throws_exception_on_incorrect_venezuelan_length(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Phone('+5841212345'); // Incompleto
    }
}
