<?php

namespace App\Application\Commissions\UseCases;

use App\Domain\Commissions\{
    Entities\SellerProfile,
    Repositories\SellerProfileRepositoryInterface
};
use App\Domain\Users\Repositories\UserRepositoryInterface;
use Exception;
use InvalidArgumentException;

class UpdateSellerCommissionRate
{
    public function __construct(
        private SellerProfileRepositoryInterface $sellerProfileRepository,
        private ?UserRepositoryInterface $userRepository = null
    ) {}

    public function execute(int $sellerId, float $newRate): SellerProfile
    {
        if ($this->userRepository !== null) {
            $user = $this->userRepository->findById($sellerId);
            if ($user === null) {
                throw new Exception("El usuario con ID {$sellerId} no existe.", 404);
            }
        }

        if ($newRate < 0.0 || $newRate > 100.0) {
            throw new InvalidArgumentException('El porcentaje de comisión debe estar entre 0.00% y 100.00%.');
        }

        $profile = $this->sellerProfileRepository->findByUserId($sellerId);
        if ($profile === null) {
            $profile = new SellerProfile(
                userId: $sellerId,
                commissionRate: $newRate
            );
        } else {
            $profile->setCommissionRate($newRate);
        }

        return $this->sellerProfileRepository->save($profile);
    }
}
