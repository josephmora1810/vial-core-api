<?php

namespace Tests\Doubles;

use App\Domain\Commissions\Entities\SellerProfile;
use App\Domain\Commissions\Repositories\SellerProfileRepositoryInterface;

class InMemorySellerProfileRepository implements SellerProfileRepositoryInterface
{
    /** @var array<int, SellerProfile> */
    private array $profiles = [];

    public function findByUserId(int $userId): ?SellerProfile
    {
        return $this->profiles[$userId] ?? null;
    }

    public function save(SellerProfile $profile): SellerProfile
    {
        $this->profiles[$profile->getUserId()] = $profile;
        return $profile;
    }
}
