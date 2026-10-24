<?php

namespace Tests\Unit\Domain\Payments;

use App\Domain\Payments\Entities\{ExchangeRate, Order, Payment};
use App\Domain\Payments\Enums\{OrderableType, OrderStatus, PaymentMethod, PaymentStatus};
use App\Domain\Payments\ValueObjects\PaymentResult;
use App\Domain\Shared\Enums\Currency;
use App\Domain\Shared\ValueObjects\{Money, Uuid};
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PaymentDomainTest extends TestCase
{
    public function testExchangeRateValidAndInvalid(): void
    {
        $rate = new ExchangeRate(Currency::USD, 'VENTA', 36.50, new DateTimeImmutable('2026-10-23'));
        $this->assertSame('venta', $rate->getType());
        $this->assertSame(36.50, $rate->getRate());
        $this->assertSame(Currency::USD, $rate->getCurrency());

        $this->expectException(InvalidArgumentException::class);
        new ExchangeRate(Currency::USD, 'invalido', 36.50);
    }

    public function testExchangeRateZeroOrNegativeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ExchangeRate(Currency::USD, 'compra', 0.0);
    }

    public function testOrderStateTransitions(): void
    {
        $order = new Order(
            id: 1,
            uuid: Uuid::generate(),
            orderNumber: 'ORD-100',
            userId: 5,
            orderableType: OrderableType::ServiceContract,
            orderableId: 20,
            amountUsd: new Money(50, 'USD'),
            exchangeRate: 36.5,
            amountVes: new Money(1825, 'VES'),
            status: OrderStatus::Pending
        );

        $this->assertFalse($order->isPaid());
        $this->assertSame(OrderStatus::Pending, $order->getStatus());

        $order->markAsPaid('BANC-REF-999');
        $this->assertTrue($order->isPaid());
        $this->assertSame('BANC-REF-999', $order->getBancaribeRef());
        $this->assertNotNull($order->getPaidAt());

        $order2 = new Order(
            id: 2,
            uuid: Uuid::generate(),
            orderNumber: 'ORD-101',
            userId: 5,
            orderableType: OrderableType::ServiceContract,
            orderableId: 20,
            amountUsd: new Money(50, 'USD'),
            exchangeRate: 36.5,
            amountVes: new Money(1825, 'VES'),
            status: OrderStatus::Pending
        );
        $order2->cancel();
        $this->assertSame(OrderStatus::Cancelled, $order2->getStatus());
    }

    public function testPaymentStateTransitions(): void
    {
        $payment = new Payment(
            orderId: 1,
            paidByUserId: 5,
            amount: 1825.0,
            paymentMethod: PaymentMethod::PagoMovil,
            status: PaymentStatus::Pending
        );

        $this->assertSame(PaymentStatus::Pending, $payment->getStatus());
        $this->assertSame('pago_movil', $payment->getPaymentMethod()->getValue());
        $this->assertSame(1825.0, $payment->getAmount()->getAmount());
    }

    public function testPaymentResultValueObject(): void
    {
        $res = new PaymentResult(
            success: true,
            message: 'Aprobado',
            transactionId: 'TX-100'
        );

        $this->assertTrue($res->success);
        $this->assertSame('TX-100', $res->transactionId);
        $this->assertSame('Aprobado', $res->message);
    }

    public function testPaymentEnums(): void
    {
        $this->assertSame(PaymentMethod::C2p, PaymentMethod::fromString('c2p'));
        $this->assertSame(PaymentMethod::PagoMovil, PaymentMethod::fromString('pago_movil'));
        $this->assertContains('c2p', PaymentMethod::values());

        $this->assertSame(PaymentStatus::Completed, PaymentStatus::fromString('completed'));
        $this->assertContains('pending', PaymentStatus::values());

        $this->assertSame(OrderStatus::Paid, OrderStatus::fromString('PAID'));
        $this->assertTrue(OrderStatus::Paid->isPaid());
        $this->assertFalse(OrderStatus::Pending->isPaid());

        $this->assertSame(OrderableType::ServiceContract, OrderableType::fromString('service_contract'));
        $this->assertSame(OrderableType::TowService, OrderableType::fromString('tow_service'));
    }
}
