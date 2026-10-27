<?php

namespace Tests\Unit\Application\UseCases;

use App\Application\Payments\UseCases\ProcessPayment;
use App\Domain\Payments\Entities\Order;
use App\Domain\Contracts\Entities\Plan;
use App\Domain\Contracts\Entities\ServiceContract;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Payments\Enums\OrderableType;
use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\FakePaymentGateway;
use Tests\Doubles\InMemoryOrderRepository;
use Tests\Doubles\InMemoryPaymentRepository;
use Tests\Doubles\InMemoryPlanRepository;
use Tests\Doubles\InMemoryServiceContractRepository;

class ProcessPaymentTest extends TestCase
{
    private InMemoryOrderRepository $orderRepo;
    private InMemoryPaymentRepository $paymentRepo;
    private InMemoryServiceContractRepository $contractRepo;
    private InMemoryPlanRepository $planRepo;
    private FakePaymentGateway $gateway;
    private ProcessPayment $useCase;

    protected function setUp(): void
    {
        $this->orderRepo = new InMemoryOrderRepository();
        $this->paymentRepo = new InMemoryPaymentRepository();
        $this->contractRepo = new InMemoryServiceContractRepository();
        $this->planRepo = new InMemoryPlanRepository();
        $this->gateway = new FakePaymentGateway();

        $this->useCase = new ProcessPayment(
            $this->orderRepo,
            $this->paymentRepo,
            $this->contractRepo,
            $this->planRepo,
            $this->gateway
        );
    }

    private function createPendingContractOrder(int $userId = 10): Order
    {
        $plan = $this->planRepo->save(new Plan(
            name: 'Plan Básico Vial 2026',
            priceUsd: 35.00,
            durationMonths: 12,
            maxServices: 2,
            maxKm: 50.00
        ));

        $contract = $this->contractRepo->save(new ServiceContract(
            clientId: $userId,
            planId: $plan->getId(),
            remainingServices: 2,
            remainingKm: 50.00,
            status: ContractStatus::PendingPayment
        ));

        return $this->orderRepo->save(new Order(
            userId: $userId,
            orderableType: OrderableType::ServiceContract,
            orderableId: $contract->getId(),
            amountUsd: 35.00,
            exchangeRate: 45.00,
            amountVes: 1575.00,
            status: OrderStatus::Pending
        ));
    }

    public function testC2pPaymentSuccessMarksOrderPaidAndActivatesServiceContract(): void
    {
        $order = $this->createPendingContractOrder(10);

        $result = $this->useCase->execute(10, $order->getUuid()->getValue(), [
            'payment_method'  => 'c2p',
            'bancoPagador'    => '0102',
            'documentType'    => 'V',
            'documentNumber'  => '12345678',
            'telefonoPersona' => '04141234567',
            'otp'             => '12345678',
        ]);

        $this->assertSame('completed', $result['payment']['status']);
        $this->assertSame('PAID', $result['order']['status']);
        $this->assertSame('ACTIVE', $result['contract']['status']);
        $this->assertNotNull($result['contract']['expires_at']);
    }

    public function testDeclinedC2pPaymentRecordsFailedPaymentAttemptAndThrows422(): void
    {
        $order = $this->createPendingContractOrder(10);

        try {
            $this->useCase->execute(10, $order->getOrderNumber(), [
                'payment_method'  => 'c2p',
                'bancoPagador'    => '0102',
                'documentType'    => 'V',
                'documentNumber'  => '12345678',
                'telefonoPersona' => '04141234567',
                'otp'             => '00000000', // Simulates declined OTP
            ]);
            $this->fail('Expected exception was not thrown.');
        } catch (Exception $e) {
            $this->assertSame(422, $e->getCode());
            $this->assertStringContainsString('Transacción declinada por el banco', $e->getMessage());
        }

        // Verify failed attempt was audited in payments table (FR-30)
        $attempts = $this->paymentRepo->findByOrderId($order->getId());
        $this->assertCount(1, $attempts);
        $this->assertSame(PaymentStatus::Failed, $attempts[0]->getStatus());
    }

    public function testPagoMovilPreventsAntiReplayWithDuplicateReference(): void
    {
        $order1 = $this->createPendingContractOrder(10);
        $order2 = $this->createPendingContractOrder(10);

        // Pay order 1 with reference 88776655
        $this->useCase->execute(10, $order1->getId(), [
            'payment_method'       => 'pago_movil',
            'reference'            => '88776655',
            'date'                 => date('Y-m-d'),
            'amount'               => 1575.00,
            'identificadorPersona' => 'V12345678',
        ]);

        // Attempt to reuse the same reference 88776655 on order 2
        $this->expectException(Exception::class);
        $this->expectExceptionCode(409);

        $this->useCase->execute(10, $order2->getId(), [
            'payment_method'       => 'pago_movil',
            'reference'            => '88776655',
            'date'                 => date('Y-m-d'),
            'amount'               => 1575.00,
            'identificadorPersona' => 'V12345678',
        ]);
    }
}
