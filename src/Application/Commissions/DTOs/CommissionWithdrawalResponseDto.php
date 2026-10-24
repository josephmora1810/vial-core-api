<?php

namespace App\Application\Commissions\DTOs;

readonly class CommissionWithdrawalResponseDto
{
    /**
     * @param list<array<string, mixed>> $commissions
     * @param array<string, mixed> $bank_details
     */
    public function __construct(
        public float $requested_amount_usd,
        public int $commissions_count,
        public array $commissions,
        public array $bank_details
    ) {}

    /**
     * @param list<array<string, mixed>> $commissions
     * @param array<string, mixed> $bankDetails
     */
    public static function create(
        float $requestedAmountUsd,
        int $commissionsCount,
        array $commissions,
        array $bankDetails
    ): self {
        return new self(
            requested_amount_usd: round($requestedAmountUsd, 2),
            commissions_count: $commissionsCount,
            commissions: $commissions,
            bank_details: $bankDetails
        );
    }

    public function toArray(): array
    {
        return [
            'requested_amount_usd' => $this->requested_amount_usd,
            'commissions_count'    => $this->commissions_count,
            'commissions'          => $this->commissions,
            'bank_details'         => $this->bank_details,
        ];
    }
}
