<?php

namespace App\Domain\Payments\ValueObjects;

readonly class PaymentResult
{
    public function __construct(
        public bool $success,
        public string $message,
        public ?string $transactionId = null,
        public ?array $requestPayload = null,
        public ?array $responsePayload = null
    ) {}
}
