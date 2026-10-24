<?php

namespace App\Application\Payments\DTOs;

readonly class WebhookResponseDto
{
    /**
     * @param array<string, mixed>|null $contract Salida de ServiceContractResponseDto::toArray()
     * @param string|null $status 'paid' | 'failed' | null (orden ya procesada, idempotente)
     */
    public function __construct(
        public OrderResponseDto $order,
        public ?array $contract,
        public ?string $status,
        public string $message
    ) {}

    /**
     * @param array<string, mixed> $data Resultado de HandleBancaribeWebhook::execute()
     */
    public static function fromArray(array $data): self
    {
        return new self(
            order: OrderResponseDto::fromArray(is_array($data['order'] ?? null) ? $data['order'] : []),
            contract: is_array($data['contract'] ?? null) ? $data['contract'] : null,
            status: isset($data['status']) ? (string)$data['status'] : null,
            message: (string)($data['message'] ?? 'Webhook procesado.')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'order'    => $this->order->toArray(),
            'contract' => $this->contract,
            'status'   => $this->status,
            'message'  => $this->message,
        ];
    }
}