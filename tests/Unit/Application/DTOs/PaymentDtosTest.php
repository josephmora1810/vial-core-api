<?php

namespace Tests\Unit\Application\DTOs;

use App\Application\Payments\DTOs\{
    BankBalanceResponseDto,
    ExchangeRateResponseDto,
    ExchangeRatesResponseDto,
    ExchangeRatesSyncResponseDto,
    IntegrationLogsResponseDto,
    OrderResponseDto,
    PaymentProcessResponseDto,
    PaymentResultResponseDto,
    WebhookResponseDto
};
use App\Domain\Payments\Entities\{ExchangeRate, Order, Payment};
use App\Domain\Payments\Enums\{OrderableType, OrderStatus, PaymentMethod, PaymentStatus};
use App\Domain\Shared\Enums\Currency;
use App\Domain\Shared\ValueObjects\{Money, Uuid};
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class PaymentDtosTest extends TestCase
{
    public function testBankBalanceResponseDtoFromArrayAndToArray(): void
    {
        $payload = [
            'success'  => true,
            'balance'  => 125000.50,
            'accounts' => [
                [
                    'account_number' => '01140001000000000001',
                    'currency_code'  => 'VES',
                    'currency_name'  => 'Bolívares',
                    'available'      => 120000.00,
                    'balance'        => 125000.50,
                    'blocked'        => 5000.50,
                ],
            ],
            'message'  => 'Saldos consultados.',
            'raw_data' => null,
        ];

        $dto = BankBalanceResponseDto::fromArray($payload);

        $this->assertTrue($dto->success);
        $this->assertSame(125000.50, $dto->balance);
        $this->assertCount(1, $dto->accounts);
        $this->assertSame('01140001000000000001', $dto->accounts[0]['account_number']);
        $this->assertSame($payload, $dto->toArray());
    }

    public function testExchangeRateResponseDtoFromEntityAndArray(): void
    {
        $entity = new ExchangeRate(
            currency: Currency::USD,
            type: 'venta',
            rate: 36.50,
            date: new DateTimeImmutable('2026-10-23')
        );

        $dto = ExchangeRateResponseDto::fromEntity($entity);
        $this->assertSame('USD', $dto->currency);
        $this->assertSame('venta', $dto->type);
        $this->assertSame(36.50, $dto->rate);
        $this->assertSame('2026-10-23', $dto->date);

        $array = $dto->toArray();
        $dtoFromArray = ExchangeRateResponseDto::fromArray($array);
        $this->assertSame($array, $dtoFromArray->toArray());
    }

    public function testExchangeRatesResponseDto(): void
    {
        $payload = [
            'usd_venta'  => 36.50,
            'usd_compra' => 36.40,
            'rates'      => [
                ['currency' => 'USD', 'type' => 'venta', 'rate' => 36.50, 'date' => '2026-10-23'],
            ],
        ];

        $dto = ExchangeRatesResponseDto::fromArray($payload);
        $this->assertSame(36.50, $dto->usd_venta);
        $this->assertSame(36.40, $dto->usd_compra);
        $this->assertCount(1, $dto->rates);
        $this->assertSame($payload, $dto->toArray());
    }

    public function testExchangeRatesSyncResponseDto(): void
    {
        $ratesList = [
            ['currency' => 'USD', 'type' => 'venta', 'rate' => 36.50, 'date' => '2026-10-23'],
        ];

        $dto = ExchangeRatesSyncResponseDto::fromArray($ratesList);
        $this->assertSame(['synced_rates' => $ratesList], $dto->toArray());
    }

    public function testIntegrationLogsResponseDto(): void
    {
        $rawLogs = [
            '2026-10-23 20:00:00 [bancaribe] Request to token OK',
            '2026-10-23 20:00:01 [bancaribe] Payment C2P successful',
        ];

        $dto = IntegrationLogsResponseDto::fromLogs('bancaribe', $rawLogs);
        $this->assertSame('bancaribe', $dto->filter_service);
        $this->assertSame(2, $dto->count);
        $this->assertSame($rawLogs, $dto->logs);

        $array = $dto->toArray();
        $this->assertSame(2, $array['count']);
        $this->assertSame('bancaribe', $array['filter_service']);
    }

    public function testOrderResponseDtoFromEntity(): void
    {
        $uuid = Uuid::generate();
        $order = new Order(
            id: 10,
            uuid: $uuid,
            orderNumber: 'ORD-2026-0001',
            userId: 5,
            orderableType: OrderableType::ServiceContract,
            orderableId: 100,
            amountUsd: new Money(50.00, 'USD'),
            exchangeRate: 36.50,
            amountVes: new Money(1825.00, 'VES'),
            status: OrderStatus::Pending,
            createdAt: new DateTimeImmutable('2026-10-23 10:00:00')
        );

        $dto = OrderResponseDto::fromEntity($order);
        $this->assertSame($uuid->getValue(), $dto->id);
        $this->assertSame('ORD-2026-0001', $dto->order_number);
        $this->assertSame(50.0, $dto->amount_usd);
        $this->assertSame(1825.0, $dto->amount_ves);
        $this->assertSame('PENDING', $dto->status);
    }

    public function testPaymentResultResponseDtoFromEntity(): void
    {
        $payment = new Payment(
            orderId: 10,
            paidByUserId: 5,
            amount: new Money(1825.00, 'VES'),
            paymentMethod: PaymentMethod::C2p,
            status: PaymentStatus::Completed,
            transactionId: 'TX123456',
            paidAt: new DateTimeImmutable('2026-10-23 10:05:00'),
            uuid: Uuid::generate(),
            id: 1,
            createdAt: new DateTimeImmutable('2026-10-23 10:00:00')
        );

        $dto = PaymentResultResponseDto::fromEntity($payment, 'Transacción aprobada.');
        $this->assertSame('TX123456', $dto->transaction_id);
        $this->assertSame('c2p', $dto->payment_method);
        $this->assertSame(1825.0, $dto->amount_ves);
        $this->assertSame('completed', $dto->status);
        $this->assertSame('Transacción aprobada.', $dto->bank_message);
    }

    public function testPaymentProcessResponseDtoFromArrayAndToArray(): void
    {
        $payload = [
            'payment'  => [
                'uuid'           => 'p-uuid-1',
                'transaction_id' => 'TX-99',
                'payment_method' => 'pago_movil',
                'amount_ves'     => 1825.0,
                'status'         => 'paid',
                'paid_at'        => '2026-10-23 10:05:00',
                'bank_message'   => 'Aprobado',
            ],
            'order'    => [
                'id'             => 'o-uuid-1',
                'order_number'   => 'ORD-001',
                'orderable_type' => 'service_contract',
                'orderable_id'   => 100,
                'amount_usd'     => 50.0,
                'exchange_rate'  => 36.5,
                'amount_ves'     => 1825.0,
                'bancaribe_ref'  => null,
                'status'         => 'paid',
                'paid_at'        => '2026-10-23 10:05:00',
                'created_at'     => '2026-10-23 10:00:00',
            ],
            'contract' => [
                'uuid'               => 'c-uuid-1',
                'status'             => 'ACTIVE',
                'remaining_services' => 3,
                'remaining_km'       => 100.0,
            ],
        ];

        $dto = PaymentProcessResponseDto::fromArray($payload);
        $this->assertSame('TX-99', $dto->payment->transaction_id);
        $this->assertSame('ORD-001', $dto->order->order_number);
        $this->assertSame('c-uuid-1', $dto->contract['uuid']);
        $this->assertSame($payload, $dto->toArray());
    }

    public function testWebhookResponseDtoFromArrayAndToArray(): void
    {
        $payload = [
            'order'    => [
                'id'             => 'o-uuid-1',
                'order_number'   => 'ORD-001',
                'orderable_type' => 'service_contract',
                'orderable_id'   => 100,
                'amount_usd'     => 50.0,
                'exchange_rate'  => 36.5,
                'amount_ves'     => 1825.0,
                'bancaribe_ref'  => null,
                'status'         => 'paid',
                'paid_at'        => '2026-10-23 10:05:00',
                'created_at'     => '2026-10-23 10:00:00',
            ],
            'contract' => ['uuid' => 'c-uuid-1', 'status' => 'ACTIVE'],
            'status'   => 'paid',
            'message'  => 'Liquidación confirmada exitosamente.',
        ];

        $dto = WebhookResponseDto::fromArray($payload);
        $this->assertSame('paid', $dto->status);
        $this->assertSame('Liquidación confirmada exitosamente.', $dto->message);
        $this->assertSame($payload, $dto->toArray());
    }
}
