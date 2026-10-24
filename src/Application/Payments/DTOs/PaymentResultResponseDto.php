<?php

namespace App\Application\Payments\DTOs;

use App\Domain\Payments\Entities\Payment;

readonly class PaymentResultResponseDto
{
    public function __construct(
        public string $uuid,
        public ?string $transaction_id,
        public string $payment_method,
        public float $amount_ves,
        public string $status,
        public ?string $paid_at,
        public ?string $bank_message
    ) {}

    public static function fromEntity(Payment $payment, ?string $bankMessage = null): self
    {
        return new self(
            uuid: $payment->getUuid()->getValue(),
            transaction_id: $payment->getTransactionId(),
            payment_method: $payment->getPaymentMethod()->getValue(),
            amount_ves: $payment->getAmount()->getAmount(),
            status: $payment->getStatus()->getValue(),
            paid_at: $payment->getPaidAt()?->format('Y-m-d H:i:s'),
            bank_message: $bankMessage
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            uuid: (string)($data['uuid'] ?? ''),
            transaction_id: isset($data['transaction_id']) ? (string)$data['transaction_id'] : null,
            payment_method: (string)($data['payment_method'] ?? ''),
            amount_ves: (float)($data['amount_ves'] ?? 0),
            status: (string)($data['status'] ?? ''),
            paid_at: isset($data['paid_at']) ? (string)$data['paid_at'] : null,
            bank_message: isset($data['bank_message']) ? (string)$data['bank_message'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'uuid'           => $this->uuid,
            'transaction_id' => $this->transaction_id,
            'payment_method' => $this->payment_method,
            'amount_ves'     => $this->amount_ves,
            'status'         => $this->status,
            'paid_at'        => $this->paid_at,
            'bank_message'   => $this->bank_message,
        ];
    }
}