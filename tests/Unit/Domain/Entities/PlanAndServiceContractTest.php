<?php

namespace Tests\Unit\Domain\Entities;

use App\Domain\Contracts\Entities\Plan;
use App\Domain\Contracts\Entities\ServiceContract;
use App\Domain\Contracts\Enums\ContractStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PlanAndServiceContractTest extends TestCase
{
    public function testPlanCalculatesPriceInVesAtExchangeRate(): void
    {
        $plan = new Plan(
            name: 'Plan Básico Vial 2026',
            priceUsd: 35.00,
            durationMonths: 12,
            maxServices: 2,
            maxKm: 50.00
        );

        $vesPrice = $plan->calculatePriceInVes(45.50);

        $this->assertSame(1592.50, $vesPrice->getAmount());
        $this->assertSame('VES', $vesPrice->getCurrency());
    }

    public function testPlanRejectsInvalidPriceOrQuota(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Plan(
            name: 'Plan Inválido',
            priceUsd: 0.0,
            durationMonths: 12,
            maxServices: 2,
            maxKm: 50.0
        );
    }

    public function testServiceContractActivatesAndConsumesServiceAndKm(): void
    {
        $contract = new ServiceContract(
            clientId: 1,
            planId: 1,
            remainingServices: 2,
            remainingKm: 50.00
        );

        $this->assertSame(ContractStatus::PendingPayment, $contract->getStatus());
        $this->assertFalse($contract->canCoverRescue());

        $contract->activate(12);
        $this->assertSame(ContractStatus::Active, $contract->getStatus());
        $this->assertTrue($contract->isActive());
        $this->assertTrue($contract->canCoverRescue());
        $this->assertNotNull($contract->getExpiresAt());

        $contract->consumeService(18.5);
        $this->assertSame(1, $contract->getRemainingServices());
        $this->assertSame(31.50, $contract->getRemainingKm());

        $contract->refundService(18.5);
        $this->assertSame(2, $contract->getRemainingServices());
        $this->assertSame(50.00, $contract->getRemainingKm());
    }
}
