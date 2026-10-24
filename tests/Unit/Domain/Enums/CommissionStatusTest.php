<?php

namespace Tests\Unit\Domain\Enums;

use App\Domain\Commissions\Enums\CommissionStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CommissionStatusTest extends TestCase
{
    public function testFromStringParsesAllValidStatusesCaseInsensitively(): void
    {
        $this->assertSame(CommissionStatus::Pending, CommissionStatus::fromString('PENDING'));
        $this->assertSame(CommissionStatus::Pending, CommissionStatus::fromString('pending'));
        $this->assertSame(CommissionStatus::WithdrawalRequested, CommissionStatus::fromString('withdrawal_requested'));
        $this->assertSame(CommissionStatus::Paid, CommissionStatus::fromString('paid'));
        $this->assertSame(CommissionStatus::Cancelled, CommissionStatus::fromString('cancelled'));
    }

    public function testFromStringFailsWithInvalidStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El estado de comisión "INVALID_STATUS" no es válido.');

        CommissionStatus::fromString('INVALID_STATUS');
    }

    public function testValuesAndHelperMethods(): void
    {
        $values = CommissionStatus::values();
        $this->assertContains('PENDING', $values);
        $this->assertContains('WITHDRAWAL_REQUESTED', $values);
        $this->assertContains('PAID', $values);
        $this->assertContains('CANCELLED', $values);

        $pending = CommissionStatus::Pending;
        $this->assertTrue($pending->isPending());
        $this->assertFalse($pending->isWithdrawalRequested());
        $this->assertFalse($pending->isPaid());
        $this->assertFalse($pending->isCancelled());

        $req = CommissionStatus::WithdrawalRequested;
        $this->assertTrue($req->isWithdrawalRequested());

        $paid = CommissionStatus::Paid;
        $this->assertTrue($paid->isPaid());

        $cancelled = CommissionStatus::Cancelled;
        $this->assertTrue($cancelled->isCancelled());
    }
}
