<?php

namespace App\Infrastructure\Database\Repositories\Payments;

use App\Domain\Payments\Entities\Order;
use App\Domain\Payments\Enums\OrderableType;
use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Payments\Repositories\OrderRepositoryInterface;
use App\Domain\Shared\ValueObjects\Uuid;
use DateTimeImmutable;
use PDO;

class MariaDbOrderRepository implements OrderRepositoryInterface
{
    public function __construct(private PDO $db) {}

    public function findById(int $id): ?Order
    {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    public function findByUuid(string $uuid): ?Order
    {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE uuid = :uuid LIMIT 1");
        $stmt->execute(['uuid' => trim($uuid)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    public function findByOrderNumber(string $orderNumber): ?Order
    {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE order_number = :order_number LIMIT 1");
        $stmt->execute(['order_number' => trim($orderNumber)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM orders
            WHERE user_id = :user_id
            ORDER BY id DESC
        ");
        $stmt->execute(['user_id' => $userId]);

        $orders = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $orders[] = $this->mapRow($row);
        }
        return $orders;
    }

    public function findByOrderable(string $orderableType, int $orderableId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM orders
            WHERE orderable_type = :orderable_type
              AND orderable_id = :orderable_id
            ORDER BY id DESC
        ");
        $stmt->execute([
            'orderable_type' => $orderableType,
            'orderable_id'   => $orderableId,
        ]);

        $orders = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $orders[] = $this->mapRow($row);
        }
        return $orders;
    }

    public function save(Order $order): Order
    {
        if ($order->getId() === null) {
            $stmt = $this->db->prepare("
                INSERT INTO orders (
                    uuid, order_number, user_id, orderable_type, orderable_id,
                    amount_usd, exchange_rate, amount_ves, bancaribe_ref, status, paid_at
                ) VALUES (
                    :uuid, :order_number, :user_id, :orderable_type, :orderable_id,
                    :amount_usd, :exchange_rate, :amount_ves, :bancaribe_ref, :status, :paid_at
                )
            ");
            $stmt->execute([
                'uuid'           => $order->getUuid()->getValue(),
                'order_number'   => $order->getOrderNumber(),
                'user_id'        => $order->getUserId(),
                'orderable_type' => $order->getOrderableType()->getValue(),
                'orderable_id'   => $order->getOrderableId(),
                'amount_usd'     => $order->getAmountUsd()->getAmount(),
                'exchange_rate'  => $order->getExchangeRate(),
                'amount_ves'     => $order->getAmountVes()->getAmount(),
                'bancaribe_ref'  => $order->getBancaribeRef(),
                'status'         => $order->getStatus()->getValue(),
                'paid_at'        => $order->getPaidAt()?->format('Y-m-d H:i:s'),
            ]);
            $order->setId((int)$this->db->lastInsertId());
            return $order;
        }

        $stmt = $this->db->prepare("
            UPDATE orders
            SET amount_usd = :amount_usd,
                exchange_rate = :exchange_rate,
                amount_ves = :amount_ves,
                bancaribe_ref = :bancaribe_ref,
                status = :status,
                paid_at = :paid_at
            WHERE id = :id
        ");
        $stmt->execute([
            'id'            => $order->getId(),
            'amount_usd'    => $order->getAmountUsd()->getAmount(),
            'exchange_rate' => $order->getExchangeRate(),
            'amount_ves'    => $order->getAmountVes()->getAmount(),
            'bancaribe_ref' => $order->getBancaribeRef(),
            'status'        => $order->getStatus()->getValue(),
            'paid_at'       => $order->getPaidAt()?->format('Y-m-d H:i:s'),
        ]);

        return $order;
    }

    private function mapRow(array $row): Order
    {
        return new Order(
            userId: (int)$row['user_id'],
            orderableType: OrderableType::fromString((string)$row['orderable_type']),
            orderableId: (int)$row['orderable_id'],
            amountUsd: (float)$row['amount_usd'],
            exchangeRate: (float)$row['exchange_rate'],
            amountVes: (float)$row['amount_ves'],
            orderNumber: (string)$row['order_number'],
            status: OrderStatus::fromString((string)$row['status']),
            bancaribeRef: $row['bancaribe_ref'] !== null ? (string)$row['bancaribe_ref'] : null,
            paidAt: !empty($row['paid_at']) ? new DateTimeImmutable((string)$row['paid_at']) : null,
            uuid: new Uuid((string)$row['uuid']),
            id: (int)$row['id'],
            createdAt: !empty($row['created_at']) ? new DateTimeImmutable((string)$row['created_at']) : null
        );
    }
}
