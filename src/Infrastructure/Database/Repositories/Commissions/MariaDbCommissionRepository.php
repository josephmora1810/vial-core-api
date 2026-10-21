<?php

namespace App\Infrastructure\Database\Repositories\Commissions;

use App\Domain\Commissions\Entities\Commission;
use App\Domain\Commissions\Enums\CommissionStatus;
use App\Domain\Commissions\Repositories\CommissionRepositoryInterface;
use DateTimeImmutable;
use PDO;

class MariaDbCommissionRepository implements CommissionRepositoryInterface
{
    public function __construct(private PDO $db) {}

    public function findById(int $id): ?Commission
    {
        $stmt = $this->db->prepare("SELECT * FROM commissions WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    public function findByOrderId(int $orderId): ?Commission
    {
        $stmt = $this->db->prepare("SELECT * FROM commissions WHERE order_id = :order_id LIMIT 1");
        $stmt->execute(['order_id' => $orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    public function findBySellerId(int $sellerId, ?CommissionStatus $status = null): array
    {
        $sql = "SELECT * FROM commissions WHERE seller_id = :seller_id";
        $params = ['seller_id' => $sellerId];

        if ($status !== null) {
            $sql .= " AND status = :status";
            $params['status'] = $status->getValue();
        }

        $sql .= " ORDER BY id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $commissions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $commissions[] = $this->mapRow($row);
        }
        return $commissions;
    }

    public function findAll(?CommissionStatus $status = null, ?int $sellerId = null, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM commissions WHERE 1=1";
        $params = [];

        if ($status !== null) {
            $sql .= " AND status = :status";
            $params['status'] = $status->getValue();
        }

        if ($sellerId !== null) {
            $sql .= " AND seller_id = :seller_id";
            $params['seller_id'] = $sellerId;
        }

        $sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $commissions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $commissions[] = $this->mapRow($row);
        }
        return $commissions;
    }

    public function countAll(?CommissionStatus $status = null, ?int $sellerId = null): int
    {
        $sql = "SELECT COUNT(*) FROM commissions WHERE 1=1";
        $params = [];

        if ($status !== null) {
            $sql .= " AND status = :status";
            $params['status'] = $status->getValue();
        }

        if ($sellerId !== null) {
            $sql .= " AND seller_id = :seller_id";
            $params['seller_id'] = $sellerId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function getSellerWalletSummary(int $sellerId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN status != 'CANCELLED' THEN amount_usd ELSE 0 END), 0) AS total_earned_usd,
                COALESCE(SUM(CASE WHEN status = 'PENDING' THEN amount_usd ELSE 0 END), 0) AS pending_balance_usd,
                COALESCE(SUM(CASE WHEN status = 'WITHDRAWAL_REQUESTED' THEN amount_usd ELSE 0 END), 0) AS withdrawal_requested_usd,
                COALESCE(SUM(CASE WHEN status = 'PAID' THEN amount_usd ELSE 0 END), 0) AS paid_balance_usd
            FROM commissions
            WHERE seller_id = :seller_id
        ");
        $stmt->execute(['seller_id' => $sellerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_earned_usd'         => round((float)($row['total_earned_usd'] ?? 0), 2),
            'pending_balance_usd'      => round((float)($row['pending_balance_usd'] ?? 0), 2),
            'withdrawal_requested_usd' => round((float)($row['withdrawal_requested_usd'] ?? 0), 2),
            'paid_balance_usd'         => round((float)($row['paid_balance_usd'] ?? 0), 2),
        ];
    }

    public function getGlobalWalletSummary(): array
    {
        $stmt = $this->db->query("
            SELECT
                COALESCE(SUM(CASE WHEN status != 'CANCELLED' THEN amount_usd ELSE 0 END), 0) AS total_commissions_usd,
                COALESCE(SUM(CASE WHEN status = 'PENDING' THEN amount_usd ELSE 0 END), 0) AS total_pending_usd,
                COALESCE(SUM(CASE WHEN status = 'WITHDRAWAL_REQUESTED' THEN amount_usd ELSE 0 END), 0) AS total_requested_usd,
                COALESCE(SUM(CASE WHEN status = 'PAID' THEN amount_usd ELSE 0 END), 0) AS total_paid_usd
            FROM commissions
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_commissions_usd' => round((float)($row['total_commissions_usd'] ?? 0), 2),
            'total_pending_usd'     => round((float)($row['total_pending_usd'] ?? 0), 2),
            'total_requested_usd'   => round((float)($row['total_requested_usd'] ?? 0), 2),
            'total_paid_usd'        => round((float)($row['total_paid_usd'] ?? 0), 2),
        ];
    }

    public function save(Commission $commission): Commission
    {
        if ($commission->getId() === null) {
            $stmt = $this->db->prepare("
                INSERT INTO commissions (
                    seller_id, order_id, amount_usd, percentage, status,
                    payment_reference, paid_at
                ) VALUES (
                    :seller_id, :order_id, :amount_usd, :percentage, :status,
                    :payment_reference, :paid_at
                )
            ");
            $stmt->execute([
                'seller_id'         => $commission->getSellerId(),
                'order_id'          => $commission->getOrderId(),
                'amount_usd'        => $commission->getAmountUsd(),
                'percentage'        => $commission->getPercentage(),
                'status'            => $commission->getStatus()->getValue(),
                'payment_reference' => $commission->getPaymentReference(),
                'paid_at'           => $commission->getPaidAt()?->format('Y-m-d H:i:s'),
            ]);
            $commission->setId((int)$this->db->lastInsertId());
        } else {
            $stmt = $this->db->prepare("
                UPDATE commissions SET
                    seller_id = :seller_id,
                    order_id = :order_id,
                    amount_usd = :amount_usd,
                    percentage = :percentage,
                    status = :status,
                    payment_reference = :payment_reference,
                    paid_at = :paid_at,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute([
                'id'                => $commission->getId(),
                'seller_id'         => $commission->getSellerId(),
                'order_id'          => $commission->getOrderId(),
                'amount_usd'        => $commission->getAmountUsd(),
                'percentage'        => $commission->getPercentage(),
                'status'            => $commission->getStatus()->getValue(),
                'payment_reference' => $commission->getPaymentReference(),
                'paid_at'           => $commission->getPaidAt()?->format('Y-m-d H:i:s'),
            ]);
        }

        return $this->findById($commission->getId()) ?? $commission;
    }

    private function mapRow(array $row): Commission
    {
        return new Commission(
            sellerId: (int)$row['seller_id'],
            orderId: (int)$row['order_id'],
            amountUsd: (float)$row['amount_usd'],
            percentage: (float)$row['percentage'],
            status: CommissionStatus::fromString($row['status']),
            id: (int)$row['id'],
            paymentReference: $row['payment_reference'] !== null ? (string)$row['payment_reference'] : null,
            paidAt: !empty($row['paid_at']) ? new DateTimeImmutable($row['paid_at']) : null,
            createdAt: !empty($row['created_at']) ? new DateTimeImmutable($row['created_at']) : null,
            updatedAt: !empty($row['updated_at']) ? new DateTimeImmutable($row['updated_at']) : null
        );
    }
}
