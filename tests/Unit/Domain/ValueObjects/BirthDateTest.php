<?php

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\Users\ValueObjects\BirthDate;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BirthDateTest extends TestCase
{
    public function test_can_create_valid_adult_birth_date(): void
    {
        $dob = new BirthDate('1990-05-15');

        $this->assertSame('1990-05-15', $dob->format());
        $this->assertSame('1990-05-15', (string)$dob);
        $this->assertTrue($dob->isAdult());
        $this->assertGreaterThanOrEqual(18, $dob->getAge());
    }

    public function test_can_parse_venezuelan_date_format(): void
    {
        $dob = new BirthDate('15/05/1990');

        $this->assertSame('1990-05-15', $dob->format());
    }

    public function test_can_create_from_datetime_immutable(): void
    {
        $date = new DateTimeImmutable('1985-12-01');
        $dob = BirthDate::fromDateTime($date);

        $this->assertSame('1985-12-01', $dob->format());
    }

    public function test_throws_exception_if_user_is_under_18(): void
    {
        // Fecha de alguien con 10 años
        $minorDate = (new DateTimeImmutable('today'))->modify('-10 years')->format('Y-m-d');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El usuario debe ser mayor de edad (mínimo 18 años).');

        new BirthDate($minorDate);
    }

    public function test_throws_exception_for_future_date(): void
    {
        $futureDate = (new DateTimeImmutable('today'))->modify('+1 day')->format('Y-m-d');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La fecha de nacimiento no puede ser una fecha futura.');

        new BirthDate($futureDate);
    }

    public function test_throws_exception_if_age_exceeds_120_years(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La fecha de nacimiento no es válida (supera el límite de 120 años).');

        new BirthDate('1850-01-01');
    }

    public function test_equals_returns_true_for_same_dates(): void
    {
        $dob1 = new BirthDate('1990-05-15');
        $dob2 = new BirthDate('15/05/1990');

        $this->assertTrue($dob1->equals($dob2));
    }
}
