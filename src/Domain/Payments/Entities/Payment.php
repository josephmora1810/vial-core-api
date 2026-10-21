<?php

namespace App\Domain\Payments\Entities;

use App\Domain\Shared\Enums\Currency;
use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Shared\ValueObjects\Uuid;
use DateTimeImmutable;

class Payment
{
    private Uuid $uuid;
    private Money $amount;
    private PaymentMethod $paymentMethod;
    private PaymentStatus $status;
    private DateTimeImmutable $createdAt;

    public function __construct(
        private int $orderId,
        private int $paidByUserId,
        Money|float $amount,
        PaymentMethod|string $paymentMethod,
        PaymentStatus|string $status = PaymentStatus::Pending,
        private ?string $transactionId = null,
        private string $commerce = 'Bancaribe',
        private ?array $requestPayload = null,
        private ?array $responsePayload = null,
        private ?string $errorMessage = null,
        private ?DateTimeImmutable $paidAt = null,
        ?Uuid $uuid = null,
        private ?int $id = null,
        ?DateTimeImmutable $createdAt = null
    ) {
        $this->amount = $amount instanceof Money
            ? $amount
            : new Money((float)$amount, Currency::VES);

        $this->paymentMethod = $paymentMethod instanceof PaymentMethod
            ? $paymentMethod
            : PaymentMethod::fromString($paymentMethod);

        $this->status = $status instanceof PaymentStatus
            ? $status
            : PaymentStatus::fromString($status);

        $this->uuid = $uuid ?? Uuid::v7();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function getPaidByUserId(): int
    {
        return $this->paidByUserId;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getPaymentMethod(): PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function getCommerce(): string
    {
        return $this->commerce;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function getRequestPayload(): ?array
    {
        return $this->requestPayload;
    }

    public function getResponsePayload(): ?array
    {
        return $this->responsePayload;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getPaidAt(): ?DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
