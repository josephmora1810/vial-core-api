<?php

namespace App\Application\UseCases;

use App\Application\DTOs\SavedAddressResponseDto;
use App\Domain\Repositories\SavedAddressRepositoryInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use Exception;

class ListSavedAddresses
{
    public function __construct(
        private SavedAddressRepositoryInterface $addressRepository,
        private UserRepositoryInterface $userRepository
    ) {}

    /**
     * @return array<array>
     */
    public function execute(int $userId): array
    {
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new Exception("Usuario no encontrado.", 404);
        }

        $addresses = $this->addressRepository->findAllByUserId($userId);

        return array_map(
            fn($addr) => SavedAddressResponseDto::fromEntity($addr)->toArray(),
            $addresses
        );
    }
}
