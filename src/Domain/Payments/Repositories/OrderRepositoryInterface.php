<?php

namespace App\Domain\Payments\Repositories;

use App\Domain\Payments\Entities\Order;

interface OrderRepositoryInterface
{
    public function findById(int $id): ?Order;

    public function findByUuid(string $uuid): ?Order;

    public function findByOrderNumber(string $orderNumber): ?Order;

    /** @return list<Order> */
    public function findByUserId(int $userId): array;

    /** @return list<Order> */
    public function findByOrderable(string $orderableType, int $orderableId): array;

    public function save(Order $order): Order;
}
