<?php

namespace Tests\Unit\Application\UseCases;

use App\Application\Payments\UseCases\HandleBancaribeWebhook;
use App\Domain\Payments\Entities\Order;
use App\Domain\Contracts\Entities\Plan;
use App\Domain\Contracts\Entities\ServiceContract;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Payments\Enums\OrderableType;
use App\Domain\Payments\Enums\OrderStatus;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryOrderRepository;
use Tests\Doubles\InMemoryPaymentRepository;
use Tests\Doubles\InMemoryPlanRepository;
use Tests\Doubles\InMemoryServiceContractRepository;

class HandleBancaribeWebhookTest extends TestCase
{
    private InMemoryOrderRepository $orderRepo;
    private InMemoryPaymentRepository $paymentRepo;
    private InMemoryServiceContractRepository $contractRepo;
    private InMemoryPlanRepository $planRepo;

    protected function setUp(): void
    {
        $this->orderRepo = new InMemoryOrderRepository();
        $this->paymentRepo = new InMemoryPaymentRepository();
        $this->contractRepo = new InMemoryServiceContractRepository();
        $this->planRepo = new InMemoryPlanRepository();
    }

    public function testWebhookApprovesOrderAndActivatesContract(): void
    {
        $plan = $this->planRepo->save(new Plan(name: 'Plan Plata', priceUsd: 60.00));
        $contract = $this->contractRepo->save(new ServiceContract(
            clientId: 10,
            planId: $plan->getId(),
            remainingServices: 4,
            remainingKm: 120.00,
            status: ContractStatus::PendingPayment
        ));
        $order = $this->orderRepo->save(new Order(
            userId: 10,
            orderableType: OrderableType::ServiceContract,
            orderableId: $contract->getId(),
            amountUsd: 60.00,
            exchangeRate: 45.00,
            status: OrderStatus::Pending
        ));

        $webhookUseCase = new HandleBancaribeWebhook(
            $this->orderRepo,
            $this->paymentRepo,
            $this->contractRepo,
            $this->planRepo,
            'secret-123'
        );

        $result = $webhookUseCase->execute([
            'order_number' => $order->getOrderNumber(),
            'reference'    => '99887766',
            'status'       => 'APPROVED',
        ], 'secret-123');

        $this->assertSame('paid', $result['status']);
        $this->assertSame('PAID', $result['order']['status']);
        $this->assertSame('ACTIVE', $result['contract']['status']);
    }

    public function testWebhookRejectsInvalidSecretSignature(): void
    {
        $webhookUseCase = new HandleBancaribeWebhook(
            $this->orderRepo,
            $this->paymentRepo,
            $this->contractRepo,
            $this->planRepo,
            'secret-123'
        );

        $this->expectException(Exception::class);
        $this->expectExceptionCode(401);

        $webhookUseCase->execute([
            'order_number' => 'ORD-2026-ABCDEF',
            'reference'    => '99887766',
            'status'       => 'APPROVED',
        ], 'wrong-secret');
    }

    public function testWebhookFailsWhenSecretNotConfigured(): void
    {
        $prevEnv = $_ENV['BANCARIBE_WEBHOOK_SECRET'] ?? null;
        unset($_ENV['BANCARIBE_WEBHOOK_SECRET']);

        $webhookUseCase = new HandleBancaribeWebhook(
            $this->orderRepo,
            $this->paymentRepo,
            $this->contractRepo,
            $this->planRepo,
            null
        );

        $this->expectException(Exception::class);
        $this->expectExceptionCode(500);

        try {
            $webhookUseCase->execute([
                'order_number' => 'ORD-2026-ABCDEF',
                'reference'    => '99887766',
                'status'       => 'APPROVED',
            ]);
        } finally {
            if ($prevEnv !== null) {
                $_ENV['BANCARIBE_WEBHOOK_SECRET'] = $prevEnv;
            }
        }
    }

    public function testWebhookApprovesWithHmacSha256Signature(): void
    {
        $plan = $this->planRepo->save(new Plan(name: 'Plan Oro', priceUsd: 100.00));
        $contract = $this->contractRepo->save(new ServiceContract(
            clientId: 12,
            planId: $plan->getId(),
            remainingServices: 5,
            remainingKm: 150.00,
            status: ContractStatus::PendingPayment
        ));
        $order = $this->orderRepo->save(new Order(
            userId: 12,
            orderableType: OrderableType::ServiceContract,
            orderableId: $contract->getId(),
            amountUsd: 100.00,
            exchangeRate: 45.00,
            status: OrderStatus::Pending
        ));

        $secret = 'super-secret-hmac-key';
        $webhookUseCase = new HandleBancaribeWebhook(
            $this->orderRepo,
            $this->paymentRepo,
            $this->contractRepo,
            $this->planRepo,
            $secret
        );

        $payload = [
            'order_number' => $order->getOrderNumber(),
            'reference'    => '11223344',
            'status'       => 'APPROVED',
        ];
        $rawJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hmac = hash_hmac('sha256', $rawJson, $secret);

        $result = $webhookUseCase->execute($payload, $hmac, $rawJson);
        $this->assertSame('paid', $result['status']);
        $this->assertSame('PAID', $result['order']['status']);
        $this->assertSame('ACTIVE', $result['contract']['status']);
    }
}
