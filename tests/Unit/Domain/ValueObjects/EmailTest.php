<?php

namespace Tests\Unit\ValueObjects;

use App\Domain\ValueObjects\Email;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EmailTest extends TestCase
{
    public function test_it_creates_a_valid_email(): void
    {
        $raw = 'cliente@ubikgo.com';
        $email = new Email($raw);

        $this->assertSame($raw, $email->getValue());
        $this->assertSame($raw, (string)$email);
    }

    public function test_two_emails_with_same_value_are_equal(): void
    {
        $email1 = new Email('admin@ubikgo.com');
        $email2 = new Email('admin@ubikgo.com');

        $this->assertTrue($email1->equals($email2));
    }

    public function test_it_throws_exception_for_invalid_email(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Email('invalido_sin_arroba');
    }
}
