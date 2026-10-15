<?php

namespace Tests\Doubles;

use App\Domain\Entities\SavedAddress;
use App\Domain\Repositories\SavedAddressRepositoryInterface;
use App\Domain\ValueObjects\Uuid;

class InMemorySavedAddressRepository implements SavedAddressRepositoryInterface
{
    /** @var array<int, SavedAddress> */
    private array $addresses = [];
    private int $nextId = 1;

    public function save(SavedAddress $address): void
    {
        if ($address->getId() === null) {
            $address->setId($this->nextId++);
        }
        $this->addresses[$address->getId()] = $address;
    }

    public function findById(int $id): ?SavedAddress
    {
        return $this->addresses[$id] ?? null;
    }

    public function findByUuid(Uuid $uuid): ?SavedAddress
    {
        foreach ($this->addresses as $address) {
            if ($address->getUuid()->equals($uuid)) {
                return $address;
            }
        }
        return null;
    }

    public function findAllByUserId(int $userId): array
    {
        $result = [];
        foreach ($this->addresses as $address) {
            if ($address->getUserId() === $userId) {
                $result[] = $address;
            }
        }
        return $result;
    }

    public function delete(int $id, int $userId): bool
    {
        if (isset($this->addresses[$id]) && $this->addresses[$id]->getUserId() === $userId) {
            unset($this->addresses[$id]);
            return true;
        }
        return false;
    }

    public function deleteByUuid(Uuid $uuid, int $userId): bool
    {
        foreach ($this->addresses as $id => $address) {
            if ($address->getUuid()->equals($uuid) && $address->getUserId() === $userId) {
                unset($this->addresses[$id]);
                return true;
            }
        }
        return false;
    }
}
