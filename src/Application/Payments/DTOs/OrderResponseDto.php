<?php

namespace App\Application\Payments\DTOs;

use App\Domain\Payments\Entities\Order;

readonly class OrderResponseDto
{
    public function __construct(
        public string $id,
        public string $order_number,
        public string $orderable_type,
        public int $orderable_id,
        public float $amount_usd,
        public float $exchange_rate,
        public float $amount_ves,
        public ?string $bancaribe_ref,
        public string $status,
        public ?string $paid_at,
        public string $created_at
    ) {}

    public static function fromEntity(Order $order): self
    {
        return new self(
            id: $order->getUuid()->getValue(),
            order_number: $order->getOrderNumber(),
            orderable_type: $order->getOrderableType()->getValue(),
            orderable_id: $order->getOrderableId(),
            amount_usd: $order->getAmountUsd()->getAmount(),
            exchange_rate: $order->getExchangeRate(),
            amount_ves: $order->getAmountVes()->getAmount(),
            bancaribe_ref: $order->getBancaribeRef(),
            status: $order->getStatus()->getValue(),
            paid_at: $order->getPaidAt()?->format('Y-m-d H:i:s'),
            created_at: $order->getCreatedAt()->format('Y-m-d H:i:s')
        );
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'order_number'   => $this->order_number,
            'orderable_type' => $this->orderable_type,
            'orderable_id'   => $this->orderable_id,
            'amount_usd'     => $this->amount_usd,
            'exchange_rate'  => $this->exchange_rate,
            'amount_ves'     => $this->amount_ves,
            'bancaribe_ref'  => $this->bancaribe_ref,
            'status'         => $this->status,
            'paid_at'        => $this->paid_at,
            'created_at'     => $this->created_at,
        ];
    }
}
