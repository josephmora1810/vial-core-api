<?php

namespace App\Application\Users\UseCases;

use App\Domain\Users\Repositories\SavedAddressRepositoryInterface;
use App\Domain\Shared\ValueObjects\Uuid;
use Exception;

class DeleteSavedAddress
{
    public function __construct(
        private SavedAddressRepositoryInterface $addressRepository
    ) {}

    public function execute(string $uuidString, int $userId): bool
    {
        $uuid = new Uuid($uuidString);
        $deleted = $this->addressRepository->deleteByUuid($uuid, $userId);

        if (!$deleted) {
            throw new Exception("Dirección no encontrada o no pertenece a tu cuenta.", 404);
        }

        return true;
    }
}
