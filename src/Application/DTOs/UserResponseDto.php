<?php

namespace App\Application\DTOs;

use App\Domain\Entities\User;

/**
 * Data Transfer Object que encapsula los datos públicos de un Usuario.
 * Evita la fuga de entidades de dominio y datos sensibles (contraseñas, IDs internos).
 */
readonly class UserResponseDto
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $uuid,
        public string $names,
        public string $surnames,
        public string $fullName,
        public string $identificationCard,
        public ?string $birthDate,
        public string $email,
        public string $phone,
        public string $role,
        public array $roles,
        public float $walletBalance,
        public bool $isActive,
        public string $createdAt,
        public ?string $consentAt = null
    ) {}

    public static function fromEntity(User $user): self
    {
        return new self(
            uuid: $user->getUuid()->getValue(),
            names: $user->getNames()->getValue(),
            surnames: $user->getSurnames()->getValue(),
            fullName: $user->getFullName(),
            identificationCard: $user->getIdentificationCard()->getValue(),
            birthDate: $user->getBirthDate()?->format('Y-m-d'),
            email: $user->getEmail()->getValue(),
            phone: $user->getPhone()->getValue(),
            role: $user->getRole()?->getName() ?? 'client',
            roles: $user->getRoleNames(),
            walletBalance: $user->getWalletBalance()->getAmount(),
            isActive: $user->isActive(),
            createdAt: $user->getCreatedAt()->format('Y-m-d H:i:s'),
            consentAt: $user->getConsentAt()?->format('Y-m-d H:i:s')
        );
    }

    public function toArray(): array
    {
        return [
            'uuid'                => $this->uuid,
            'names'               => $this->names,
            'surnames'            => $this->surnames,
            'full_name'           => $this->fullName,
            'identification_card' => $this->identificationCard,
            'birth_date'          => $this->birthDate,
            'email'               => $this->email,
            'phone'               => $this->phone,
            'role'                => $this->role,
            'roles'               => $this->roles,
            'wallet_balance'      => $this->walletBalance,
            'is_active'           => $this->isActive,
            'created_at'          => $this->createdAt,
            'consent_at'          => $this->consentAt,
        ];
    }
}
