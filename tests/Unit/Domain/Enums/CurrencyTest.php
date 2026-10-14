<?php

namespace Tests\Unit\Domain\Enums;

use App\Domain\Enums\Currency;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CurrencyTest extends TestCase
{
    public function test_can_instantiate_valid_currencies(): void
    {
        $this->assertSame(Currency::VES, Currency::fromString('ves'));
        $this->assertSame(Currency::USD, Currency::fromString('USD'));
        $this->assertSame(Currency::EUR, Currency::fromString(' eur '));

        $this->assertSame('VES', Currency::VES->getValue());
        $this->assertSame('USD', Currency::USD->getValue());
        $this->assertSame('EUR', Currency::EUR->getValue());
    }

    public function test_throws_exception_on_invalid_currency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La moneda "BTC" no es válida.');

        Currency::fromString('BTC');
    }

    public function test_returns_all_expected_values(): void
    {
        $expected = ['VES', 'USD', 'EUR'];
        $this->assertSame($expected, Currency::values());
    }
}
