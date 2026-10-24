<?php

namespace App\Application\Commissions\UseCases;

use App\Domain\Commissions\{
    Enums\CommissionStatus,
    Repositories\CommissionRepositoryInterface,
    Repositories\SellerProfileRepositoryInterface
};
use App\Domain\Payments\Repositories\OrderRepositoryInterface;
use App\Domain\Users\Repositories\UserRepositoryInterface;

class ListAdminCommissions
{
    public function __construct(
        private CommissionRepositoryInterface $commissionRepository,
        private SellerProfileRepositoryInterface $sellerProfileRepository,
        private ?UserRepositoryInterface $userRepository = null,
        private ?OrderRepositoryInterface $orderRepository = null
    ) {}

    /**
     * @return array{
     *   summary: array<string, float>,
     *   pagination: array{total: int, limit: int, offset: int},
     *   commissions: list<array<string, mixed>>
     * }
     */
    public function execute(?string $status = null, ?int $sellerId = null, int $limit = 50, int $offset = 0): array
    {
        $statusEnum = null;
        if (!empty($status)) {
            $statusEnum = CommissionStatus::fromString($status);
        }

        $summary = $this->commissionRepository->getGlobalWalletSummary();
        $total = $this->commissionRepository->countAll($statusEnum, $sellerId);
        $commissions = $this->commissionRepository->findAll($statusEnum, $sellerId, $limit, $offset);

        $items = [];
        foreach ($commissions as $comm) {
            $data = $comm->toArray();

            // Enriquecer con datos del vendedor
            if ($this->userRepository !== null) {
                $sellerUser = $this->userRepository->findById($comm->getSellerId());
                if ($sellerUser !== null) {
                    $data['seller'] = [
                        'id'                  => $sellerUser->getId(),
                        'name'                => $sellerUser->getFullName(),
                        'identification_card' => $sellerUser->getIdentificationCard()->getValue(),
                        'email'               => $sellerUser->getEmail()->getValue(),
                    ];
                }
            }

            // Enriquecer con datos bancarios del vendedor
            $profile = $this->sellerProfileRepository->findByUserId($comm->getSellerId());
            if ($profile !== null) {
                $data['seller_bank'] = [
                    'bank_id'             => $profile->getBankId(),
                    'account_number'      => $profile->getAccountNumber(),
                    'account_type'        => $profile->getAccountType(),
                    'account_holder_name' => $profile->getAccountHolderName(),
                    'account_holder_id'   => $profile->getAccountHolderId(),
                    'phone_pago_movil'    => $profile->getPhonePagoMovil(),
                ];
            }

            // Enriquecer con datos de la orden
            if ($this->orderRepository !== null) {
                $order = $this->orderRepository->findById($comm->getOrderId());
                if ($order !== null) {
                    $data['order'] = [
                        'order_number' => $order->getOrderNumber(),
                        'amount_usd'   => $order->getAmountUsd()->getAmount(),
                        'amount_ves'   => $order->getAmountVes()->getAmount(),
                    ];
                }
            }

            $items[] = $data;
        }

        return [
            'summary'    => $summary,
            'pagination' => [
                'total'  => $total,
                'limit'  => $limit,
                'offset' => $offset,
            ],
            'commissions' => $items,
        ];
    }
}
