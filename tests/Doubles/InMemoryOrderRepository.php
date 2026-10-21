<?php

namespace Tests\Doubles;

use App\Domain\Payments\Entities\Order;
use App\Domain\Payments\Repositories\OrderRepositoryInterface;

class InMemoryOrderRepository implements OrderRepositoryInterface
{
    /** @var array<int, Order> */
    private array $orders = [];
    private int $nextId = 1;

    public function findById(int $id): ?Order
    {
        return $this->orders[$id] ?? null;
    }

    public function findByUuid(string $uuid): ?Order
    {
        foreach ($this->orders as $order) {
            if ($order->getUuid()->getValue() === strtolower(trim($uuid))) {
                return $order;
            }
        }
        return null;
    }

    public function findByOrderNumber(string $orderNumber): ?Order
    {
        foreach ($this->orders as $order) {
            if ($order->getOrderNumber() === trim($orderNumber)) {
                return $order;
            }
        }
        return null;
    }

    public function findByUserId(int $userId): array
    {
        $result = [];
        foreach ($this->orders as $order) {
            if ($order->getUserId() === $userId) {
                $result[] = $order;
            }
        }
        return $result;
    }

    public function findByOrderable(string $orderableType, int $orderableId): array
    {
        $result = [];
        foreach ($this->orders as $order) {
            if ($order->getOrderableType()->getValue() === $orderableType && $order->getOrderableId() === $orderableId) {
                $result[] = $order;
            }
        }
        return array_reverse($result);
    }

    public function save(Order $order): Order
    {
        if ($order->getId() === null) {
            $order->setId($this->nextId++);
        }
        $this->orders[$order->getId()] = $order;
        return $order;
    }
}
