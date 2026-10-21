<?php

namespace App\Application\Commissions\UseCases;

use App\Domain\Commissions\Entities\Commission;
use App\Domain\Commissions\Repositories\CommissionRepositoryInterface;
use App\Domain\Commissions\Repositories\SellerProfileRepositoryInterface;
use Exception;
use InvalidArgumentException;

class PayCommissionByAdmin
{
    public function __construct(
        private CommissionRepositoryInterface $commissionRepository,
        private ?SellerProfileRepositoryInterface $sellerProfileRepository = null
    ) {}

    public function execute(int $commissionId, string $paymentReference): Commission
    {
        $cleanRef = trim($paymentReference);
        if (empty($cleanRef)) {
            throw new InvalidArgumentException('La referencia de pago bancaria es obligatoria para liquidar la comisión.');
        }

        $commission = $this->commissionRepository->findById($commissionId);
        if ($commission === null) {
            throw new Exception("La comisión con ID {$commissionId} no existe.", 404);
        }

        $commission->markAsPaid($cleanRef);
        return $this->commissionRepository->save($commission);
    }
}
