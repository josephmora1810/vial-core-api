<?php

namespace Tests\Unit\Domain\Entities;

use App\Domain\Commissions\Entities\Commission;
use App\Domain\Commissions\Enums\CommissionStatus;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CommissionTest extends TestCase
{
    public function testCanInstantiateCommissionWithDefaults(): void
    {
        $commission = new Commission(
            sellerId: 10,
            orderId: 101,
            amountUsd: 25.50,
            percentage: 10.00
        );

        $this->assertNull($commission->getId());
        $this->assertSame(10, $commission->getSellerId());
        $this->assertSame(101, $commission->getOrderId());
        $this->assertSame(25.50, $commission->getAmountUsd());
        $this->assertSame(10.00, $commission->getPercentage());
        $this->assertSame(CommissionStatus::Pending, $commission->getStatus());
        $this->assertNull($commission->getPaymentReference());
        $this->assertNull($commission->getPaidAt());
        $this->assertNull($commission->getCreatedAt());
        $this->assertNull($commission->getUpdatedAt());
    }

    public function testSetIdWorks(): void
    {
        $commission = new Commission(10, 101, 15.00, 10.00);
        $commission->setId(42);
        $this->assertSame(42, $commission->getId());
    }

    public function testThrowsExceptionWhenAmountIsNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El monto de la comisión en USD no puede ser negativo.');

        new Commission(10, 101, -5.00, 10.00);
    }

    public function testThrowsExceptionWhenPercentageIsInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El porcentaje de comisión debe estar entre 0.00% y 100.00%.');

        new Commission(10, 101, 10.00, 150.00);
    }

    public function testRequestWithdrawalTransitionsToWithdrawalRequested(): void
    {
        $commission = new Commission(10, 101, 20.00, 10.00);
        $this->assertSame(CommissionStatus::Pending, $commission->getStatus());

        $commission->requestWithdrawal();

        $this->assertSame(CommissionStatus::WithdrawalRequested, $commission->getStatus());
        $this->assertInstanceOf(DateTimeImmutable::class, $commission->getUpdatedAt());
    }

    public function testRequestWithdrawalFailsWhenNotPending(): void
    {
        $commission = new Commission(
            sellerId: 10,
            orderId: 101,
            amountUsd: 20.00,
            percentage: 10.00,
            status: CommissionStatus::Paid
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Solo las comisiones en estado PENDING pueden ser solicitadas para retiro.');

        $commission->requestWithdrawal();
    }

    public function testMarkAsPaidTransitionsToPaid(): void
    {
        $commission = new Commission(10, 101, 20.00, 10.00);
        $paidDate = new DateTimeImmutable('2026-05-01 12:00:00');

        $commission->markAsPaid('REF-BANCO-998877', $paidDate);

        $this->assertSame(CommissionStatus::Paid, $commission->getStatus());
        $this->assertSame('REF-BANCO-998877', $commission->getPaymentReference());
        $this->assertSame($paidDate, $commission->getPaidAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $commission->getUpdatedAt());
    }

    public function testMarkAsPaidFailsWhenReferenceIsEmpty(): void
    {
        $commission = new Commission(10, 101, 20.00, 10.00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La referencia de pago bancaria es obligatoria');

        $commission->markAsPaid('   ');
    }

    public function testMarkAsPaidFailsWhenAlreadyPaid(): void
    {
        $commission = new Commission(
            sellerId: 10,
            orderId: 101,
            amountUsd: 20.00,
            percentage: 10.00,
            status: CommissionStatus::Paid
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Esta comisión ya fue pagada previamente.');

        $commission->markAsPaid('REF-123');
    }

    public function testMarkAsPaidFailsWhenCancelled(): void
    {
        $commission = new Commission(
            sellerId: 10,
            orderId: 101,
            amountUsd: 20.00,
            percentage: 10.00,
            status: CommissionStatus::Cancelled
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No se puede pagar una comisión que ha sido cancelada.');

        $commission->markAsPaid('REF-123');
    }

    public function testCancelTransitionsToCancelled(): void
    {
        $commission = new Commission(10, 101, 20.00, 10.00);
        $commission->cancel();

        $this->assertSame(CommissionStatus::Cancelled, $commission->getStatus());
        $this->assertInstanceOf(DateTimeImmutable::class, $commission->getUpdatedAt());
    }

    public function testCancelFailsWhenAlreadyPaid(): void
    {
        $commission = new Commission(
            sellerId: 10,
            orderId: 101,
            amountUsd: 20.00,
            percentage: 10.00,
            status: CommissionStatus::Paid
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No se puede cancelar una comisión que ya fue pagada.');

        $commission->cancel();
    }

    public function testToArrayFormatsCorrectly(): void
    {
        $createdAt = new DateTimeImmutable('2026-01-10 10:00:00');
        $commission = new Commission(
            sellerId: 15,
            orderId: 205,
            amountUsd: 30.00,
            percentage: 12.00,
            status: CommissionStatus::Pending,
            id: 8,
            createdAt: $createdAt
        );

        $array = $commission->toArray();

        $this->assertSame(8, $array['id']);
        $this->assertSame(15, $array['seller_id']);
        $this->assertSame(205, $array['order_id']);
        $this->assertSame(30.00, $array['amount_usd']);
        $this->assertSame(12.00, $array['percentage']);
        $this->assertSame('PENDING', $array['status']);
        $this->assertNull($array['payment_reference']);
        $this->assertSame('2026-01-10 10:00:00', $array['created_at']);
    }
}
