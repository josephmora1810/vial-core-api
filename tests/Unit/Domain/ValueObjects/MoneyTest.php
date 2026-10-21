<?php

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\Shared\Enums\Currency;
use App\Domain\Shared\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_can_create_valid_money(): void
    {
        $money = new Money(50.25, Currency::USD);

        $this->assertSame(50.25, $money->getAmount());
        $this->assertSame('USD', $money->getCurrency());
        $this->assertSame(Currency::USD, $money->getCurrencyEnum());
    }

    public function test_defaults_to_ves(): void
    {
        $money = new Money(100.0);

        $this->assertSame('VES', $money->getCurrency());
    }

    public function test_arithmetic_operations(): void
    {
        $money = new Money(100.0, Currency::USD);

        $added = $money->add(new Money(50.0, Currency::USD));
        $this->assertSame(150.0, $added->getAmount());

        $subtracted = $money->subtract(new Money(30.0, Currency::USD));
        $this->assertSame(70.0, $subtracted->getAmount());

        $multiplied = $money->multiply(2.5);
        $this->assertSame(250.0, $multiplied->getAmount());

        $divided = $money->divide(4);
        $this->assertSame(25.0, $divided->getAmount());
    }

    public function test_equality(): void
    {
        $m1 = new Money(20.0, 'USD');
        $m2 = new Money(20.0, Currency::USD);
        $m3 = new Money(20.0, Currency::VES);

        $this->assertTrue($m1->equals($m2));
        $this->assertFalse($m1->equals($m3));
    }

    public function test_throws_exception_for_negative_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El valor debe ser mayor o igual a 0.');

        new Money(-5.0, Currency::USD);
    }
}
