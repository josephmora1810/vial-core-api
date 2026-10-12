<?php

namespace App\Domain\Entities;

use App\Domain\ValueObjects\Email;
use App\Domain\ValueObjects\LastNames;
use App\Domain\ValueObjects\Money;
use App\Domain\ValueObjects\Names;
use App\Domain\ValueObjects\NationalId;
use App\Domain\ValueObjects\Passport;
use App\Domain\ValueObjects\Password;
use App\Domain\ValueObjects\Phone;
use App\Domain\ValueObjects\Uuid;
use DateTimeImmutable;

class User
{
    /**
     * @param Role[] $roles
     * @param Permission[] $permissions
     */
    public function __construct(
        private ?int $id,
        private Uuid $uuid,
        private Names $names,
        private LastNames $lastNames,
        private Email $email,
        private ?Password $password,
        private Phone $phone,
        private Money $walletBalance,
        private NationalId $identificationCard,
        private ?DateTimeImmutable $birthDate = null,
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
        LastNames $lastNames,
        Email $email,
        ?Password $password,
        Phone $phone,
        NationalId $identificationCard,
        ?DateTimeImmutable $birthDate = null,
        ?Money $walletBalance = null
    ): self {
        return new self(
            id: null,
            uuid: $uuid,
            names: $names,
            lastNames: $lastNames,
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

    public function getLastNames(): LastNames
    {
        return $this->lastNames;
    }

    /**
     * Retorna el nombre completo formateado (Nombres + Apellidos)
     */
    public function getFullName(): string
    {
        return sprintf('%s %s', $this->names->getValue(), $this->lastNames->getValue());
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

    public function getBirthDate(): ?DateTimeImmutable
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

    // --- Gestión de Roles ---

    /**
     * Asigna un rol al usuario evitando duplicados
     */
    public function assignToRole(Role $role): self
    {
        if (!$this->hasRole($role)) {
            $this->roles[] = $role;
            $this->touch();
        }

        return $this;
    }

    public function assignRole(Role $role): self
    {
        return $this->assignToRole($role);
    }

    public function removeRole(Role $role): self
    {
        $this->roles = array_values(
            array_filter($this->roles, function (Role $r) use ($role) {
                if ($r->getId() !== null && $role->getId() !== null) {
                    return $r->getId() !== $role->getId();
                }
                return $r->getName() !== $role->getName();
            })
        );
        $this->touch();

        return $this;
    }

    public function hasRole(Role|string $role): bool
    {
        $roleName = $role instanceof Role ? $role->getName() : $role;

        foreach ($this->roles as $r) {
            if ($r instanceof Role && $r->getName() === $roleName) {
                return true;
            }
            if (is_string($r) && $r === $roleName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Role[]
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * Retorna el rol principal del usuario (el primero asignado o null)
     */
    public function getRole(): ?Role
    {
        return $this->roles[0] ?? null;
    }

    /**
     * @return string[]
     */
    public function getRoleNames(): array
    {
        return array_map(function (Role|string $role) {
            return $role instanceof Role ? $role->getName() : (string) $role;
        }, $this->roles);
    }

    // --- Gestión de Permisos ---

    public function givePermissionTo(Permission $permission): self
    {
        if (!$this->hasDirectPermission($permission)) {
            $this->permissions[] = $permission;
            $this->touch();
        }

        return $this;
    }

    public function revokePermission(Permission $permission): self
    {
        $this->permissions = array_values(
            array_filter($this->permissions, function (Permission $p) use ($permission) {
                if ($p->getId() !== null && $permission->getId() !== null) {
                    return $p->getId() !== $permission->getId();
                }
                return $p->getName() !== $permission->getName();
            })
        );
        $this->touch();

        return $this;
    }

    public function hasDirectPermission(Permission|string $permission): bool
    {
        $permName = $permission instanceof Permission ? $permission->getName() : $permission;

        foreach ($this->permissions as $p) {
            if ($p instanceof Permission && $p->getName() === $permName) {
                return true;
            }
            if (is_string($p) && $p === $permName) {
                return true;
            }
        }

        return false;
    }

    public function hasPermissionTo(Permission|string $permission): bool
    {
        if ($this->hasDirectPermission($permission)) {
            return true;
        }

        $permName = $permission instanceof Permission ? $permission->getName() : $permission;
        foreach ($this->roles as $role) {
            if ($role instanceof Role && method_exists($role, 'hasPermissionTo') && $role->hasPermissionTo($permName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Permission[]
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    // --- Acciones de Dominio ---

    public function changePassword(Password $newPassword): self
    {
        $this->password = $newPassword;
        $this->touch();
        return $this;
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