<?php

namespace App\Domain\Users\Repositories;

use App\Domain\Users\Entities\SavedAddress;
use App\Domain\Shared\ValueObjects\Uuid;

interface SavedAddressRepositoryInterface
{
    public function save(SavedAddress $address): void;

    public function findById(int $id): ?SavedAddress;

    public function findByUuid(Uuid $uuid): ?SavedAddress;

    /**
     * @return SavedAddress[]
     */
    public function findAllByUserId(int $userId): array;

    public function delete(int $id, int $userId): bool;

    public function deleteByUuid(Uuid $uuid, int $userId): bool;
}
