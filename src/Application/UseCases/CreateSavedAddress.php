<?php

namespace App\Application\UseCases;

use App\Application\DTOs\SavedAddressResponseDto;
use App\Domain\Entities\SavedAddress;
use App\Domain\Repositories\SavedAddressRepositoryInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\ValueObjects\Coordinates;
use Exception;

class CreateSavedAddress
{
    public function __construct(
        private SavedAddressRepositoryInterface $addressRepository,
        private UserRepositoryInterface $userRepository
    ) {}

    public function execute(
        int $userId,
        string $label,
        string $addressLine,
        float $latitude,
        float $longitude,
        bool $isFavorite = false
    ): array {
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new Exception("Usuario no encontrado.", 404);
        }

        if (empty(trim($label))) {
            throw new Exception("La etiqueta o nombre de la ubicación es obligatoria.", 400);
        }

        if (empty(trim($addressLine))) {
            throw new Exception("La dirección textual o punto de referencia es obligatorio.", 400);
        }

        // Valida invariantes mediante Value Object Coordinates (-90 a 90, -180 a 180)
        $coordinates = new Coordinates($latitude, $longitude);

        $address = SavedAddress::create(
            userId: $userId,
            label: $label,
            addressLine: $addressLine,
            coordinates: $coordinates,
            isFavorite: $isFavorite
        );

        $this->addressRepository->save($address);

        return SavedAddressResponseDto::fromEntity($address)->toArray();
    }
}
