<?php

namespace Tests\Doubles;

use App\Domain\Payments\Entities\Payment;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Repositories\PaymentRepositoryInterface;

class InMemoryPaymentRepository implements PaymentRepositoryInterface
{
    /** @var array<int, Payment> */
    private array $payments = [];
    private int $nextId = 1;

    public function findById(int $id): ?Payment
    {
        return $this->payments[$id] ?? null;
    }

    public function findByUuid(string $uuid): ?Payment
    {
        foreach ($this->payments as $payment) {
            if ($payment->getUuid()->getValue() === strtolower(trim($uuid))) {
                return $payment;
            }
        }
        return null;
    }

    public function existsCompletedByTransactionId(string $transactionId): bool
    {
        foreach ($this->payments as $payment) {
            if (
                $payment->getTransactionId() === trim($transactionId)
                && $payment->getStatus() === PaymentStatus::Completed
            ) {
                return true;
            }
        }
        return false;
    }

    public function findByOrderId(int $orderId): array
    {
        $result = [];
        foreach ($this->payments as $payment) {
            if ($payment->getOrderId() === $orderId) {
                $result[] = $payment;
            }
        }
        return $result;
    }

    public function save(Payment $payment): Payment
    {
        if ($payment->getId() === null) {
            $payment->setId($this->nextId++);
        }
        $this->payments[$payment->getId()] = $payment;
        return $payment;
    }
}
