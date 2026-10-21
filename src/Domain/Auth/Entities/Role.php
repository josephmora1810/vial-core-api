<?php

namespace App\Domain\Auth\Entities;
use App\Domain\Auth\Entities\Permission;
use App\Domain\Users\Entities\User;

class Role {
	public function __construct(
		private ?int $id,
		private string $name,
		private string $guardName = 'api',
        private array $permissions = [],
        private array $users = []
	) {}

    public function getId(): ?int {
        return $this->id;
    }
    
    public function getName(): string
    {
        return $this->name;
    }

    public function value(): string
    {
        return $this->name;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getGuardName(): string
    {
        return $this->guardName;
    }

    public function givePermissionTo(Permission $permission): void {
        $this->permissions[] = $permission;
    }

    /**
     * @return Permission[]
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function hasPermissionTo(Permission|string $permission): bool
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

    public function user(User $user): void {
        $this->users[] = $user;
    }
}