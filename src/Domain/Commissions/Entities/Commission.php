<?php

namespace App\Domain\Commissions\Entities;

use App\Domain\Commissions\Enums\CommissionStatus;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

class Commission
{
    public function __construct(
        private int $sellerId,
        private int $orderId,
        private float $amountUsd,
        private float $percentage,
        private CommissionStatus $status = CommissionStatus::Pending,
        private ?int $id = null,
        private ?string $paymentReference = null,
        private ?DateTimeImmutable $paidAt = null,
        private ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null
    ) {
        if ($this->amountUsd < 0.0) {
            throw new InvalidArgumentException('El monto de la comisión en USD no puede ser negativo.');
        }
        if ($this->percentage < 0.0 || $this->percentage > 100.0) {
            throw new InvalidArgumentException('El porcentaje de comisión debe estar entre 0.00% y 100.00%.');
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getSellerId(): int
    {
        return $this->sellerId;
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function getAmountUsd(): float
    {
        return $this->amountUsd;
    }

    public function getPercentage(): float
    {
        return $this->percentage;
    }

    public function getStatus(): CommissionStatus
    {
        return $this->status;
    }

    public function getPaymentReference(): ?string
    {
        return $this->paymentReference;
    }

    public function getPaidAt(): ?DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function requestWithdrawal(): void
    {
        if (!$this->status->isPending()) {
            throw new DomainException(sprintf(
                'Solo las comisiones en estado PENDING pueden ser solicitadas para retiro. Estado actual: %s.',
                $this->status->getValue()
            ));
        }
        $this->status = CommissionStatus::WithdrawalRequested;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function markAsPaid(string $reference, ?DateTimeImmutable $paidAt = null): void
    {
        $cleanRef = trim($reference);
        if (empty($cleanRef)) {
            throw new InvalidArgumentException('La referencia de pago bancaria es obligatoria para liquidar la comisión.');
        }

        if ($this->status->isPaid()) {
            throw new DomainException('Esta comisión ya fue pagada previamente.');
        }

        if ($this->status->isCancelled()) {
            throw new DomainException('No se puede pagar una comisión que ha sido cancelada.');
        }

        $this->status = CommissionStatus::Paid;
        $this->paymentReference = $cleanRef;
        $this->paidAt = $paidAt ?? new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function cancel(): void
    {
        if ($this->status->isPaid()) {
            throw new DomainException('No se puede cancelar una comisión que ya fue pagada.');
        }
        $this->status = CommissionStatus::Cancelled;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'seller_id'         => $this->sellerId,
            'order_id'          => $this->orderId,
            'amount_usd'        => $this->amountUsd,
            'percentage'        => $this->percentage,
            'status'            => $this->status->getValue(),
            'payment_reference' => $this->paymentReference,
            'paid_at'           => $this->paidAt?->format('Y-m-d H:i:s'),
            'created_at'        => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at'        => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
