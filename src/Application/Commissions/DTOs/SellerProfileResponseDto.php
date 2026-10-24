<?php

namespace App\Application\Commissions\DTOs;

use App\Domain\Commissions\Entities\SellerProfile;

readonly class SellerProfileResponseDto
{
    public function __construct(
        public int $user_id,
        public float $commission_rate,
        public ?int $bank_id = null,
        public ?string $account_number = null,
        public string $account_type = 'CORRIENTE',
        public ?string $account_holder_name = null,
        public ?string $account_holder_id = null,
        public ?string $phone_pago_movil = null,
        public bool $has_bank_details = false,
        public ?string $created_at = null,
        public ?string $updated_at = null
    ) {}

    public static function fromEntity(SellerProfile $profile): self
    {
        return new self(
            user_id: $profile->getUserId(),
            commission_rate: $profile->getCommissionRate(),
            bank_id: $profile->getBankId(),
            account_number: $profile->getAccountNumber(),
            account_type: $profile->getAccountType(),
            account_holder_name: $profile->getAccountHolderName(),
            account_holder_id: $profile->getAccountHolderId(),
            phone_pago_movil: $profile->getPhonePagoMovil(),
            has_bank_details: $profile->hasCompleteBankDetails(),
            created_at: $profile->getCreatedAt()?->format('Y-m-d H:i:s'),
            updated_at: $profile->getUpdatedAt()?->format('Y-m-d H:i:s')
        );
    }

    public function toArray(): array
    {
        return [
            'user_id'             => $this->user_id,
            'commission_rate'     => $this->commission_rate,
            'bank_id'             => $this->bank_id,
            'account_number'      => $this->account_number,
            'account_type'        => $this->account_type,
            'account_holder_name' => $this->account_holder_name,
            'account_holder_id'   => $this->account_holder_id,
            'phone_pago_movil'    => $this->phone_pago_movil,
            'has_bank_details'    => $this->has_bank_details,
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
        ];
    }
}
