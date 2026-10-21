<?php

namespace App\Domain\Payments\Entities;

use App\Domain\Shared\Enums\Currency;
use App\Domain\Payments\Enums\OrderableType;
use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Shared\ValueObjects\Uuid;
use DateTimeImmutable;
use InvalidArgumentException;

class Order
{
    private Uuid $uuid;
    private OrderableType $orderableType;
    private OrderStatus $status;
    private Money $amountUsd;
    private Money $amountVes;
    private DateTimeImmutable $createdAt;

    public function __construct(
        private int $userId,
        OrderableType|string $orderableType,
        private int $orderableId,
        Money|float $amountUsd,
        private float $exchangeRate,
        Money|float|null $amountVes = null,
        private ?string $orderNumber = null,
        OrderStatus|string $status = OrderStatus::Pending,
        private ?string $bancaribeRef = null,
        private ?DateTimeImmutable $paidAt = null,
        ?Uuid $uuid = null,
        private ?int $id = null,
        ?DateTimeImmutable $createdAt = null
    ) {
        if ($this->exchangeRate <= 0) {
            throw new InvalidArgumentException('La tasa de cambio de la orden debe ser mayor a 0.');
        }

        $this->orderableType = $orderableType instanceof OrderableType
            ? $orderableType
            : OrderableType::fromString($orderableType);

        $this->status = $status instanceof OrderStatus
            ? $status
            : OrderStatus::fromString($status);

        $this->amountUsd = $amountUsd instanceof Money
            ? $amountUsd
            : new Money((float)$amountUsd, Currency::USD);

        if ($this->amountUsd->getAmount() <= 0) {
            throw new InvalidArgumentException('El monto en USD de la orden debe ser mayor a 0.');
        }

        if ($amountVes === null) {
            $calculatedVes = round($this->amountUsd->getAmount() * $this->exchangeRate, 2);
            $this->amountVes = new Money($calculatedVes, Currency::VES);
        } else {
            $this->amountVes = $amountVes instanceof Money
                ? $amountVes
                : new Money((float)$amountVes, Currency::VES);
        }

        $this->uuid = $uuid ?? Uuid::v7();
        $this->orderNumber = $this->orderNumber ?: ('ORD-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3))));
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

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getOrderableType(): OrderableType
    {
        return $this->orderableType;
    }

    public function getOrderableId(): int
    {
        return $this->orderableId;
    }

    public function getAmountUsd(): Money
    {
        return $this->amountUsd;
    }

    public function getExchangeRate(): float
    {
        return $this->exchangeRate;
    }

    public function getAmountVes(): Money
    {
        return $this->amountVes;
    }

    public function getBancaribeRef(): ?string
    {
        return $this->bancaribeRef;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getPaidAt(): ?DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isPaid(): bool
    {
        return $this->status === OrderStatus::Paid;
    }

    public function markAsPaid(string $bancaribeRef): void
    {
        $bancaribeRef = trim($bancaribeRef);
        if ($bancaribeRef === '') {
            throw new InvalidArgumentException('La referencia bancaria de Bancaribe es obligatoria para marcar la orden como pagada.');
        }

        $this->status = OrderStatus::Paid;
        $this->bancaribeRef = $bancaribeRef;
        $this->paidAt = new DateTimeImmutable();
    }

    public function markAsFailed(): void
    {
        $this->status = OrderStatus::Failed;
    }

    public function cancel(): void
    {
        if ($this->isPaid()) {
            throw new InvalidArgumentException('No se puede cancelar una orden que ya ha sido pagada.');
        }
        $this->status = OrderStatus::Cancelled;
    }

    /**
     * Si la orden aún no ha sido pagada y la tasa BCV del día cambió,
     * actualiza la tasa y recalcula el monto en VES para evitar cobrar con tasa vencida.
     */
    public function refreshExchangeRate(float $currentRate): bool
    {
        if ($this->isPaid() || $currentRate <= 0) {
            return false;
        }

        if (abs($this->exchangeRate - $currentRate) < 0.0001) {
            return false;
        }

        $this->exchangeRate = $currentRate;
        $calculatedVes = round($this->amountUsd->getAmount() * $this->exchangeRate, 2);
        $this->amountVes = new Money($calculatedVes, Currency::VES);

        return true;
    }
}
