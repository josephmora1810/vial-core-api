<?php

namespace App\Application\Commissions\UseCases;

use App\Domain\Commissions\{
    Enums\CommissionStatus,
    Repositories\CommissionRepositoryInterface,
    Repositories\SellerProfileRepositoryInterface
};
use App\Domain\Contracts\Repositories\PlanRepositoryInterface;
use App\Domain\Payments\Repositories\OrderRepositoryInterface;

class ListSellerCommissions
{
    public function __construct(
        private CommissionRepositoryInterface $commissionRepository,
        private SellerProfileRepositoryInterface $sellerProfileRepository,
        private ?OrderRepositoryInterface $orderRepository = null,
        private ?PlanRepositoryInterface $planRepository = null
    ) {}

    /**
     * @return array{
     *   wallet: array<string, float>,
     *   seller_profile: array<string, mixed>|null,
     *   bank: array<string, mixed>|null,
     *   commissions: list<array<string, mixed>>
     * }
     */
    public function execute(int $sellerId, ?string $statusFilter = null): array
    {
        $statusEnum = null;
        if (!empty($statusFilter)) {
            $statusEnum = CommissionStatus::fromString($statusFilter);
        }

        $wallet = $this->commissionRepository->getSellerWalletSummary($sellerId);
        $profile = $this->sellerProfileRepository->findByUserId($sellerId);
        $commissions = $this->commissionRepository->findBySellerId($sellerId, $statusEnum);

        $bankInfo = null;
        if ($profile !== null && $profile->getBankId() !== null && $this->planRepository !== null) {
            $banks = $this->planRepository->getBanks(onlyActive: false);
            foreach ($banks as $b) {
                if ((int)$b['id'] === $profile->getBankId()) {
                    $bankInfo = [
                        'id'   => (int)$b['id'],
                        'code' => (string)$b['code'],
                        'name' => (string)$b['name'],
                    ];
                    break;
                }
            }
        }

        $commissionsData = [];
        foreach ($commissions as $comm) {
            $item = $comm->toArray();
            if ($this->orderRepository !== null) {
                $order = $this->orderRepository->findById($comm->getOrderId());
                if ($order !== null) {
                    $item['order_number'] = $order->getOrderNumber();
                    $item['order_amount_usd'] = $order->getAmountUsd()->getAmount();
                }
            }
            $commissionsData[] = $item;
        }

        return [
            'wallet'         => $wallet,
            'seller_profile' => $profile?->toArray(),
            'bank'           => $bankInfo,
            'commissions'    => $commissionsData,
        ];
    }
}
