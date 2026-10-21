<?php

namespace App\Domain\Commissions\Repositories;

use App\Domain\Commissions\Entities\Commission;
use App\Domain\Commissions\Enums\CommissionStatus;

interface CommissionRepositoryInterface
{
    public function findById(int $id): ?Commission;

    public function findByOrderId(int $orderId): ?Commission;

    /**
     * @return Commission[]
     */
    public function findBySellerId(int $sellerId, ?CommissionStatus $status = null): array;

    /**
     * @return Commission[]
     */
    public function findAll(?CommissionStatus $status = null, ?int $sellerId = null, int $limit = 50, int $offset = 0): array;

    public function countAll(?CommissionStatus $status = null, ?int $sellerId = null): int;

    /**
     * Devuelve el resumen financiero de la billetera del vendedor:
     * [
     *   'total_earned_usd'          => float,
     *   'pending_balance_usd'       => float,
     *   'withdrawal_requested_usd'  => float,
     *   'paid_balance_usd'          => float,
     * ]
     *
     * @return array<string, float>
     */
    public function getSellerWalletSummary(int $sellerId): array;

    /**
     * Devuelve el resumen financiero global para administración:
     * [
     *   'total_commissions_usd'     => float,
     *   'total_pending_usd'         => float,
     *   'total_requested_usd'       => float,
     *   'total_paid_usd'            => float,
     * ]
     *
     * @return array<string, float>
     */
    public function getGlobalWalletSummary(): array;

    public function save(Commission $commission): Commission;
}
