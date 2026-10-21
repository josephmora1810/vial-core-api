<?php

namespace App\Application\Users\UseCases;

use App\Application\Users\DTOs\SavedAddressResponseDto;
use App\Domain\Users\Repositories\SavedAddressRepositoryInterface;
use App\Domain\Users\Repositories\UserRepositoryInterface;
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
