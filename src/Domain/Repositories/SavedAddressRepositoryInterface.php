<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\SavedAddress;
use App\Domain\ValueObjects\Uuid;

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
