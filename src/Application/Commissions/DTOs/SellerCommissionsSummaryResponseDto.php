<?php

namespace App\Application\Commissions\DTOs;

readonly class SellerCommissionsSummaryResponseDto
{
    /**
     * @param array<string, float> $wallet
     * @param array<string, mixed>|null $seller_profile
     * @param array<string, mixed>|null $bank
     * @param list<array<string, mixed>> $commissions
     */
    public function __construct(
        public array $wallet,
        public ?array $seller_profile,
        public ?array $bank,
        public array $commissions
    ) {}

    public static function create(
        array $wallet,
        ?array $sellerProfile,
        ?array $bank,
        array $commissions
    ): self {
        return new self(
            wallet: $wallet,
            seller_profile: $sellerProfile,
            bank: $bank,
            commissions: $commissions
        );
    }

    public function toArray(): array
    {
        return [
            'wallet'         => $this->wallet,
            'seller_profile' => $this->seller_profile,
            'bank'           => $this->bank,
            'commissions'    => $this->commissions,
        ];
    }
}
