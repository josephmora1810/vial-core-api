<?php

namespace App\Domain\Auth\Entities;
use App\Domain\Auth\Entities\Role;
use App\Domain\Users\Entities\User;

class Permission {
	public function __construct(
		private ?int $id,
		private string $name,
		private string $guardName = 'api',
        private array $roles = [],
        private array $users = []
	) {}

    public function getId(): ?int {
        return $this->id;
    }
    
    public function getName(): string
    {
        return $this->name;
    }

    public function getGuardName(): string
    {
        return $this->guardName;
    }

    public function assignToRole(Role $role): void {
        $this->roles[] = $role;
    }

    public function assignedToUser(User $user): void {
        $this->users[] = $user;
    }
}