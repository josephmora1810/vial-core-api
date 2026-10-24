<?php

namespace App\Application\Commissions\UseCases;

use App\Domain\Commissions\{
    Entities\Commission,
    Enums\CommissionStatus,
    Repositories\CommissionRepositoryInterface,
    Repositories\SellerProfileRepositoryInterface
};
use App\Domain\Contracts\Entities\ServiceContract;
use App\Domain\Payments\Entities\Order;

class GenerateOrderCommission
{
    public function __construct(
        private CommissionRepositoryInterface $commissionRepository,
        private SellerProfileRepositoryInterface $sellerProfileRepository
    ) {}

    public function execute(Order $order, ServiceContract $contract): ?Commission
    {
        $sellerId = $contract->getSellerId();
        if ($sellerId === null) {
            return null;
        }

        if ($order->getId() === null) {
            return null;
        }

        // Idempotencia: si ya existe comisión para esta orden, devolverla directamente
        $existing = $this->commissionRepository->findByOrderId($order->getId());
        if ($existing !== null) {
            return $existing;
        }

        // Obtener porcentaje pactado para el vendedor (o 10.00% por defecto)
        $sellerProfile = $this->sellerProfileRepository->findByUserId($sellerId);
        $rate = $sellerProfile !== null ? $sellerProfile->getCommissionRate() : 10.00;

        $amountUsd = round($order->getAmountUsd()->getAmount() * ($rate / 100), 2);

        $commission = new Commission(
            sellerId: $sellerId,
            orderId: $order->getId(),
            amountUsd: $amountUsd,
            percentage: $rate,
            status: CommissionStatus::Pending
        );

        return $this->commissionRepository->save($commission);
    }
}
