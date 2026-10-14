<?php

namespace Tests\Unit\Domain\Enums;

use App\Domain\Enums\UserRole;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UserRoleTest extends TestCase
{
    public function test_can_instantiate_valid_roles(): void
    {
        $this->assertSame(UserRole::Admin, UserRole::fromString('admin'));
        $this->assertSame(UserRole::Driver, UserRole::fromString('driver'));
        $this->assertSame(UserRole::Client, UserRole::fromString('CLIENT'));
        $this->assertSame(UserRole::Seller, UserRole::fromString(' seller '));

        $this->assertTrue(UserRole::Admin->isAdmin());
        $this->assertTrue(UserRole::Driver->isDriver());
        $this->assertTrue(UserRole::Client->isClient());
        $this->assertTrue(UserRole::Seller->isSeller());
    }

    public function test_throws_exception_on_invalid_role(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El rol "superman" no es válido.');

        UserRole::fromString('superman');
    }

    public function test_returns_all_expected_values(): void
    {
        $expected = ['admin', 'driver', 'client', 'seller'];
        $this->assertSame($expected, UserRole::values());
    }
}
