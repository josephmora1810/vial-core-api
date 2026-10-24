<?php

namespace App\Application\Commissions\UseCases;

use App\Domain\Commissions\{
    Entities\Commission,
    Enums\CommissionStatus,
    Repositories\CommissionRepositoryInterface,
    Repositories\SellerProfileRepositoryInterface
};
use Exception;

class RequestCommissionWithdrawal
{
    public function __construct(
        private CommissionRepositoryInterface $commissionRepository,
        private SellerProfileRepositoryInterface $sellerProfileRepository
    ) {}

    /**
     * @param int $sellerId
     * @param int[] $commissionIds
     * @return array{
     *   requested_amount_usd: float,
     *   commissions_count: int,
     *   commissions: list<array<string, mixed>>,
     *   bank_details: array<string, mixed>
     * }
     */
    public function execute(int $sellerId, array $commissionIds = []): array
    {
        $profile = $this->sellerProfileRepository->findByUserId($sellerId);
        if ($profile === null || !$profile->hasCompleteBankDetails()) {
            throw new Exception(
                'Debe configurar sus datos bancarios nacionales completos (banco, número de cuenta de 20 dígitos, titular y pago móvil) antes de solicitar el retiro de comisiones.',
                422
            );
        }

        /** @var Commission[] $targets */
        $targets = [];

        if (!empty($commissionIds)) {
            $uniqueIds = array_values(array_unique(array_map('intval', $commissionIds)));
            foreach ($uniqueIds as $cid) {
                $comm = $this->commissionRepository->findById($cid);
                if ($comm === null || $comm->getSellerId() !== $sellerId) {
                    throw new Exception("La comisión con ID {$cid} no existe o no pertenece a su cuenta.", 404);
                }
                if (!$comm->getStatus()->isPending()) {
                    throw new Exception(sprintf(
                        'La comisión #%d no está en estado PENDING (estado actual: %s).',
                        $cid,
                        $comm->getStatus()->getValue()
                    ), 422);
                }
                $targets[] = $comm;
            }
        } else {
            $targets = $this->commissionRepository->findBySellerId($sellerId, CommissionStatus::Pending);
        }

        if (empty($targets)) {
            throw new Exception('No posee comisiones pendientes disponibles para solicitar retiro.', 422);
        }

        $totalRequested = 0.0;
        $updatedCommissions = [];

        foreach ($targets as $commission) {
            $commission->requestWithdrawal();
            $saved = $this->commissionRepository->save($commission);
            $totalRequested += $saved->getAmountUsd();
            $updatedCommissions[] = $saved->toArray();
        }

        return [
            'requested_amount_usd' => round($totalRequested, 2),
            'commissions_count'    => count($updatedCommissions),
            'commissions'          => $updatedCommissions,
            'bank_details'         => [
                'bank_id'             => $profile->getBankId(),
                'account_number'      => $profile->getAccountNumber(),
                'account_type'        => $profile->getAccountType(),
                'account_holder_name' => $profile->getAccountHolderName(),
                'account_holder_id'   => $profile->getAccountHolderId(),
                'phone_pago_movil'    => $profile->getPhonePagoMovil(),
            ],
        ];
    }
}
