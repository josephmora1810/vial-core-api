<?php

namespace App\Domain\Payments\Repositories;

use App\Domain\Payments\Entities\Payment;

interface PaymentRepositoryInterface
{
    public function findById(int $id): ?Payment;

    public function findByUuid(string $uuid): ?Payment;

    public function existsCompletedByTransactionId(string $transactionId): bool;

    /** @return list<Payment> */
    public function findByOrderId(int $orderId): array;

    public function save(Payment $payment): Payment;
}
