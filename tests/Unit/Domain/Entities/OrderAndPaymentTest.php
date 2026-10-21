<?php

namespace Tests\Unit\Domain\Entities;

use App\Domain\Payments\Entities\Order;
use App\Domain\Payments\Entities\Payment;
use App\Domain\Payments\Enums\OrderableType;
use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PaymentStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class OrderAndPaymentTest extends TestCase
{
    public function testOrderCalculatesAmountVesAndMarksAsPaid(): void
    {
        $order = new Order(
            userId: 5,
            orderableType: OrderableType::ServiceContract,
            orderableId: 10,
            amountUsd: 60.00,
            exchangeRate: 45.00
        );

        $this->assertSame(OrderStatus::Pending, $order->getStatus());
        $this->assertSame(2700.00, $order->getAmountVes()->getAmount());
        $this->assertFalse($order->isPaid());
        $this->assertStringStartsWith('ORD-', $order->getOrderNumber());

        $order->markAsPaid('12345678');
        $this->assertTrue($order->isPaid());
        $this->assertSame(OrderStatus::Paid, $order->getStatus());
        $this->assertSame('12345678', $order->getBancaribeRef());
        $this->assertNotNull($order->getPaidAt());
    }

    public function testPaidOrderCannotBeCancelled(): void
    {
        $order = new Order(
            userId: 5,
            orderableType: OrderableType::ServiceContract,
            orderableId: 10,
            amountUsd: 35.00,
            exchangeRate: 45.00
        );
        $order->markAsPaid('REF-001');

        $this->expectException(InvalidArgumentException::class);
        $order->cancel();
    }

    public function testPaymentEntityStoresAuditPayloads(): void
    {
        $payment = new Payment(
            orderId: 1,
            paidByUserId: 5,
            amount: 1575.00,
            paymentMethod: PaymentMethod::C2p,
            status: PaymentStatus::Failed,
            transactionId: null,
            commerce: 'Bancaribe',
            requestPayload: ['bancoPagador' => '0102'],
            responsePayload: ['code' => 400],
            errorMessage: 'Clave dinámica expirada'
        );

        $this->assertSame(PaymentMethod::C2p, $payment->getPaymentMethod());
        $this->assertSame(PaymentStatus::Failed, $payment->getStatus());
        $this->assertSame('Clave dinámica expirada', $payment->getErrorMessage());
        $this->assertSame(['bancoPagador' => '0102'], $payment->getRequestPayload());
    }
}
