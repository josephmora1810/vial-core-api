<?php

namespace Tests\Unit\Domain\Enums;

use App\Domain\Enums\DocumentStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DocumentStatusTest extends TestCase
{
    public function test_can_instantiate_valid_statuses(): void
    {
        $this->assertSame(DocumentStatus::Pending, DocumentStatus::fromString('pending'));
        $this->assertSame(DocumentStatus::Verified, DocumentStatus::fromString('VERIFIED'));
        $this->assertSame(DocumentStatus::Rejected, DocumentStatus::fromString(' rejected '));

        $this->assertTrue(DocumentStatus::Pending->isPending());
        $this->assertTrue(DocumentStatus::Verified->isVerified());
        $this->assertTrue(DocumentStatus::Rejected->isRejected());
    }

    public function test_throws_exception_on_invalid_status(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El estado "UNKNOWN" no es válido.');

        DocumentStatus::fromString('UNKNOWN');
    }

    public function test_returns_all_expected_values(): void
    {
        $expected = ['PENDING', 'VERIFIED', 'REJECTED'];
        $this->assertSame($expected, DocumentStatus::values());
    }
}
