<?php

namespace App\Domain\Users\Entities;



use App\Domain\Auth\Entities\Permission;
use App\Domain\Auth\Entities\Role;
use App\Domain\Auth\Entities\Concerns\HasRolesAndPermissions;
use App\Domain\Users\ValueObjects\BirthDate;
use App\Domain\Users\ValueObjects\Email;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Users\ValueObjects\Names;
use App\Domain\Users\ValueObjects\NationalId;
use App\Domain\Users\ValueObjects\Passport;
use App\Domain\Auth\ValueObjects\Password;
use App\Domain\Users\ValueObjects\Phone;
use App\Domain\Users\ValueObjects\Surnames;
use App\Domain\Shared\ValueObjects\Uuid;
use DateTimeImmutable;

class User
{
    use HasRolesAndPermissions;

    /**
     * @param Role[] $roles
     * @param Permission[] $permissions
     */
    public function __construct(
        private ?int $id,
        private Uuid $uuid,
        private Names $names,
        private Surnames $surnames,
        private Email $email,
        private ?Password $password,
        private Phone $phone,
        private Money $walletBalance,
        private NationalId $identificationCard,
        private ?BirthDate $birthDate = null,
        private ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null,
        private ?DateTimeImmutable $consentAt = null,
        private ?DateTimeImmutable $deletedAt = null,
        private bool $isActive = true,
        private array $roles = [],
        private array $permissions = []
    ) {
        $this->createdAt ??= new DateTimeImmutable();
    }

    /**
     * Constructor nombrado para crear un nuevo usuario en el dominio
     */
    public static function create(
        Uuid $uuid,
        Names $names,
        Surnames $surnames,
        Email $email,
        ?Password $password,
        Phone $phone,
        NationalId $identificationCard,
        ?BirthDate $birthDate = null,
        ?Money $walletBalance = null
    ): self {
        return new self(
            id: null,
            uuid: $uuid,
            names: $names,
            surnames: $surnames,
            email: $email,
            password: $password,
            phone: $phone,
            walletBalance: $walletBalance ?? new Money(0.00, 'VES'),
            identificationCard: $identificationCard,
            birthDate: $birthDate,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            consentAt: null,
            deletedAt: null,
            isActive: true,
            roles: [],
            permissions: []
        );
    }

    // --- Getters y Setters Básicos ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getNames(): Names
    {
        return $this->names;
    }

    public function getSurnames(): Surnames
    {
        return $this->surnames;
    }

    /**
     * Retorna el nombre completo formateado (Nombres + Apellidos)
     */
    public function getFullName(): string
    {
        return \sprintf('%s %s', $this->names->getValue(), $this->surnames->getValue());
    }

    /**
     * Alias compatible con llamadas existentes (ej: repositorios)
     */
    public function getName(): string
    {
        return $this->getFullName();
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getPassword(): ?Password
    {
        return $this->password;
    }

    public function getPhone(): Phone
    {
        return $this->phone;
    }

    public function getWalletBalance(): Money
    {
        return $this->walletBalance;
    }

    public function getIdentificationCard(): NationalId
    {
        return $this->identificationCard;
    }

    public function getBirthDate(): ?BirthDate
    {
        return $this->birthDate;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getConsentAt(): ?DateTimeImmutable
    {
        return $this->consentAt;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    // --- Acciones de Dominio ---

    public function changePassword(Password $newPassword): self
    {
        $this->password = $newPassword;
        $this->touch();
        return $this;
    }

    public function setPassword(Password $password): self
    {
        return $this->changePassword($password);
    }

    public function updateWalletBalance(Money $newBalance): self
    {
        $this->walletBalance = $newBalance;
        $this->touch();
        return $this;
    }

    public function updatePhone(Phone $newPhone): self
    {
        $this->phone = $newPhone;
        $this->touch();
        return $this;
    }

    public function activate(): self
    {
        $this->isActive = true;
        $this->touch();
        return $this;
    }

    public function deactivate(): self
    {
        $this->isActive = false;
        $this->touch();
        return $this;
    }

    public function grantConsent(): self
    {
        $this->consentAt = new DateTimeImmutable();
        $this->touch();
        return $this;
    }

    public function softDelete(): self
    {
        $this->deletedAt = new DateTimeImmutable();
        $this->isActive = false;
        $this->touch();
        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}