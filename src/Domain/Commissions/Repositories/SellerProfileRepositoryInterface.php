<?php

namespace App\Domain\Commissions\Repositories;

use App\Domain\Commissions\Entities\SellerProfile;

interface SellerProfileRepositoryInterface
{
    public function findByUserId(int $userId): ?SellerProfile;

    public function save(SellerProfile $profile): SellerProfile;
}
