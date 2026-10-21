<?php

namespace Tests\Doubles;

use App\Domain\Commissions\Entities\Commission;
use App\Domain\Commissions\Enums\CommissionStatus;
use App\Domain\Commissions\Repositories\CommissionRepositoryInterface;

class InMemoryCommissionRepository implements CommissionRepositoryInterface
{
    /** @var array<int, Commission> */
    private array $commissions = [];
    private int $nextId = 1;

    public function findById(int $id): ?Commission
    {
        return $this->commissions[$id] ?? null;
    }

    public function findByOrderId(int $orderId): ?Commission
    {
        foreach ($this->commissions as $comm) {
            if ($comm->getOrderId() === $orderId) {
                return $comm;
            }
        }
        return null;
    }

    public function findBySellerId(int $sellerId, ?CommissionStatus $status = null): array
    {
        $filtered = array_filter($this->commissions, function (Commission $c) use ($sellerId, $status) {
            if ($c->getSellerId() !== $sellerId) {
                return false;
            }
            if ($status !== null && $c->getStatus() !== $status) {
                return false;
            }
            return true;
        });

        // Orden descendente por id
        usort($filtered, fn(Commission $a, Commission $b) => ($b->getId() ?? 0) <=> ($a->getId() ?? 0));
        return array_values($filtered);
    }

    public function findAll(?CommissionStatus $status = null, ?int $sellerId = null, int $limit = 50, int $offset = 0): array
    {
        $filtered = array_filter($this->commissions, function (Commission $c) use ($status, $sellerId) {
            if ($status !== null && $c->getStatus() !== $status) {
                return false;
            }
            if ($sellerId !== null && $c->getSellerId() !== $sellerId) {
                return false;
            }
            return true;
        });

        usort($filtered, fn(Commission $a, Commission $b) => ($b->getId() ?? 0) <=> ($a->getId() ?? 0));
        return array_slice(array_values($filtered), $offset, $limit);
    }

    public function countAll(?CommissionStatus $status = null, ?int $sellerId = null): int
    {
        return count($this->findAll($status, $sellerId, 999999, 0));
    }

    public function getSellerWalletSummary(int $sellerId): array
    {
        $totalEarned = 0.0;
        $pending = 0.0;
        $requested = 0.0;
        $paid = 0.0;

        foreach ($this->commissions as $c) {
            if ($c->getSellerId() !== $sellerId) {
                continue;
            }
            if (!$c->getStatus()->isCancelled()) {
                $totalEarned += $c->getAmountUsd();
            }
            if ($c->getStatus()->isPending()) {
                $pending += $c->getAmountUsd();
            } elseif ($c->getStatus()->isWithdrawalRequested()) {
                $requested += $c->getAmountUsd();
            } elseif ($c->getStatus()->isPaid()) {
                $paid += $c->getAmountUsd();
            }
        }

        return [
            'total_earned_usd'         => round($totalEarned, 2),
            'pending_balance_usd'      => round($pending, 2),
            'withdrawal_requested_usd' => round($requested, 2),
            'paid_balance_usd'         => round($paid, 2),
        ];
    }

    public function getGlobalWalletSummary(): array
    {
        $totalEarned = 0.0;
        $pending = 0.0;
        $requested = 0.0;
        $paid = 0.0;

        foreach ($this->commissions as $c) {
            if (!$c->getStatus()->isCancelled()) {
                $totalEarned += $c->getAmountUsd();
            }
            if ($c->getStatus()->isPending()) {
                $pending += $c->getAmountUsd();
            } elseif ($c->getStatus()->isWithdrawalRequested()) {
                $requested += $c->getAmountUsd();
            } elseif ($c->getStatus()->isPaid()) {
                $paid += $c->getAmountUsd();
            }
        }

        return [
            'total_commissions_usd' => round($totalEarned, 2),
            'total_pending_usd'     => round($pending, 2),
            'total_requested_usd'   => round($requested, 2),
            'total_paid_usd'        => round($paid, 2),
        ];
    }

    public function save(Commission $commission): Commission
    {
        if ($commission->getId() === null) {
            $commission->setId($this->nextId++);
        }
        $this->commissions[$commission->getId()] = $commission;
        return $commission;
    }
}
