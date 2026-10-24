<?php

namespace Tests\Unit\Application\DTOs;

use App\Application\Commissions\DTOs\{
    AdminCommissionsSummaryResponseDto,
    CommissionResponseDto,
    CommissionWithdrawalResponseDto,
    SellerCommissionsSummaryResponseDto,
    SellerProfileResponseDto
};
use App\Domain\Commissions\Entities\{Commission, SellerProfile};
use App\Domain\Commissions\Enums\CommissionStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class CommissionDtosTest extends TestCase
{
    public function testCommissionResponseDtoFromEntity(): void
    {
        $paidAt = new DateTimeImmutable('2026-06-01 15:30:00');
        $commission = new Commission(
            sellerId: 10,
            orderId: 50,
            amountUsd: 25.00,
            percentage: 10.00,
            status: CommissionStatus::Paid,
            id: 1,
            paymentReference: 'PAGO-MOVIL-12345',
            paidAt: $paidAt
        );

        $seller = ['id' => 10, 'name' => 'Valeria Moreno'];
        $bank = ['bank_id' => 1, 'account_number' => '01020000000000000001'];
        $order = ['order_number' => 'ORD-2026-0001', 'amount_usd' => 250.00];

        $dto = CommissionResponseDto::fromEntity($commission, $seller, $bank, $order);
        $array = $dto->toArray();

        $this->assertSame(1, $dto->id);
        $this->assertSame(10, $dto->seller_id);
        $this->assertSame(50, $dto->order_id);
        $this->assertSame(25.00, $dto->amount_usd);
        $this->assertSame(10.00, $dto->percentage);
        $this->assertSame('PAID', $dto->status);
        $this->assertSame('PAGO-MOVIL-12345', $dto->payment_reference);
        $this->assertSame('2026-06-01 15:30:00', $dto->paid_at);
        $this->assertSame($seller, $array['seller']);
        $this->assertSame($bank, $array['seller_bank']);
        $this->assertSame($order, $array['order']);
    }

    public function testSellerProfileResponseDtoFromEntity(): void
    {
        $profile = new SellerProfile(
            userId: 10,
            commissionRate: 15.00,
            bankId: 2,
            accountNumber: '01050000000000000002',
            accountType: 'AHORRO',
            accountHolderName: 'Valeria Moreno',
            accountHolderId: 'V-18765432',
            phonePagoMovil: '04141234567'
        );

        $dto = SellerProfileResponseDto::fromEntity($profile);
        $array = $dto->toArray();

        $this->assertSame(10, $dto->user_id);
        $this->assertSame(15.00, $dto->commission_rate);
        $this->assertSame(2, $dto->bank_id);
        $this->assertSame('01050000000000000002', $dto->account_number);
        $this->assertSame('AHORRO', $dto->account_type);
        $this->assertTrue($dto->has_bank_details);
        $this->assertSame('04141234567', $array['phone_pago_movil']);
    }

    public function testCommissionWithdrawalResponseDto(): void
    {
        $dto = CommissionWithdrawalResponseDto::create(
            requestedAmountUsd: 75.50,
            commissionsCount: 3,
            commissions: [['id' => 1], ['id' => 2], ['id' => 3]],
            bankDetails: ['bank_id' => 1]
        );

        $array = $dto->toArray();
        $this->assertSame(75.50, $dto->requested_amount_usd);
        $this->assertSame(3, $dto->commissions_count);
        $this->assertCount(3, $array['commissions']);
        $this->assertSame(1, $array['bank_details']['bank_id']);
    }

    public function testSellerCommissionsSummaryResponseDto(): void
    {
        $wallet = ['total_earned_usd' => 100.00, 'pending_balance_usd' => 40.00];
        $profile = ['commission_rate' => 10.00];
        $bank = ['name' => 'Banco de Venezuela'];
        $commissions = [['id' => 1, 'amount_usd' => 40.00]];

        $dto = SellerCommissionsSummaryResponseDto::create($wallet, $profile, $bank, $commissions);
        $array = $dto->toArray();

        $this->assertSame($wallet, $array['wallet']);
        $this->assertSame($profile, $array['seller_profile']);
        $this->assertSame($bank, $array['bank']);
        $this->assertSame($commissions, $array['commissions']);
    }

    public function testAdminCommissionsSummaryResponseDto(): void
    {
        $summary = ['total_commissions_usd' => 500.00, 'total_pending_usd' => 150.00];
        $pagination = ['total' => 20, 'limit' => 10, 'offset' => 0];
        $commissions = [['id' => 1]];

        $dto = AdminCommissionsSummaryResponseDto::create($summary, $pagination, $commissions);
        $array = $dto->toArray();

        $this->assertSame($summary, $array['summary']);
        $this->assertSame($pagination, $array['pagination']);
        $this->assertSame($commissions, $array['commissions']);
    }
}
