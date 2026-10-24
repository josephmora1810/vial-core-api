<?php

namespace App\Application\Commissions\DTOs;

use App\Domain\Commissions\Entities\Commission;

readonly class CommissionResponseDto
{
    public function __construct(
        public int $id,
        public int $seller_id,
        public int $order_id,
        public float $amount_usd,
        public float $percentage,
        public string $status,
        public ?string $payment_reference = null,
        public ?string $paid_at = null,
        public ?string $created_at = null,
        public ?string $updated_at = null,
        public ?array $seller = null,
        public ?array $seller_bank = null,
        public ?array $order = null
    ) {}

    public static function fromEntity(
        Commission $commission,
        ?array $seller = null,
        ?array $sellerBank = null,
        ?array $order = null
    ): self {
        return new self(
            id: (int)$commission->getId(),
            seller_id: $commission->getSellerId(),
            order_id: $commission->getOrderId(),
            amount_usd: $commission->getAmountUsd(),
            percentage: $commission->getPercentage(),
            status: $commission->getStatus()->getValue(),
            payment_reference: $commission->getPaymentReference(),
            paid_at: $commission->getPaidAt()?->format('Y-m-d H:i:s'),
            created_at: $commission->getCreatedAt()?->format('Y-m-d H:i:s'),
            updated_at: $commission->getUpdatedAt()?->format('Y-m-d H:i:s'),
            seller: $seller,
            seller_bank: $sellerBank,
            order: $order
        );
    }

    public function toArray(): array
    {
        $data = [
            'id'                => $this->id,
            'seller_id'         => $this->seller_id,
            'order_id'          => $this->order_id,
            'amount_usd'        => $this->amount_usd,
            'percentage'        => $this->percentage,
            'status'            => $this->status,
            'payment_reference' => $this->payment_reference,
            'paid_at'           => $this->paid_at,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];

        if ($this->seller !== null) {
            $data['seller'] = $this->seller;
        }

        if ($this->seller_bank !== null) {
            $data['seller_bank'] = $this->seller_bank;
        }

        if ($this->order !== null) {
            $data['order'] = $this->order;
        }

        return $data;
    }
}
