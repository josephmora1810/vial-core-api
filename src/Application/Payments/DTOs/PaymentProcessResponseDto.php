<?php

namespace App\Application\Payments\DTOs;

readonly class PaymentProcessResponseDto
{
    /**
     * @param array<string, mixed>|null $contract Salida de ServiceContractResponseDto::toArray() + pdf_download_url
     */
    public function __construct(
        public PaymentResultResponseDto $payment,
        public OrderResponseDto $order,
        public ?array $contract = null
    ) {}

    /**
     * @param array<string, mixed> $data Resultado de ProcessPayment::execute()
     */
    public static function fromArray(array $data): self
    {
        return new self(
            payment: PaymentResultResponseDto::fromArray(is_array($data['payment'] ?? null) ? $data['payment'] : []),
            order: OrderResponseDto::fromArray(is_array($data['order'] ?? null) ? $data['order'] : []),
            contract: is_array($data['contract'] ?? null) ? $data['contract'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'payment'  => $this->payment->toArray(),
            'order'    => $this->order->toArray(),
            'contract' => $this->contract,
        ];
    }
}