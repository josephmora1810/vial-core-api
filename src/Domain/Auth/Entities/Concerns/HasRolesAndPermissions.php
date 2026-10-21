<?php

namespace App\Domain\Auth\Entities\Concerns;

use App\Domain\Auth\Entities\Permission;
use App\Domain\Auth\Entities\Role;

/**
 * Trait de Dominio que encapsula el Control de Acceso Basado en Roles y Permisos (RBAC).
 * Desacopla la lógica de autorización de la entidad User respetando el Principio de Responsabilidad Única (SRP).
 */
trait HasRolesAndPermissions
{
    /**
     * Asigna un rol al usuario evitando duplicados.
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

    /**
     * Remueve un rol del usuario.
     */
    public function removeRole(Role|string $role): self
    {
        $roleName = $role instanceof Role ? $role->getName() : $role;
        $roleId = $role instanceof Role ? $role->getId() : null;

        $this->roles = array_values(
            array_filter($this->roles, function (Role $r) use ($roleId, $roleName) {
                if ($r->getId() !== null && $roleId !== null) {
                    return $r->getId() !== $roleId;
                }
                return $r->getName() !== $roleName;
            })
        );
        $this->touch();

        return $this;
    }

    /**
     * Verifica si el usuario posee un rol específico.
     */
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
     * Verifica si el usuario posee al menos uno de los roles dados.
     *
     * @param list<Role|string> $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
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
     * Retorna el rol principal del usuario (el primero asignado o null).
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

    /**
     * Asigna un permiso directo al usuario.
     */
    public function givePermissionTo(Permission $permission): self
    {
        if (!$this->hasDirectPermission($permission)) {
            $this->permissions[] = $permission;
            $this->touch();
        }

        return $this;
    }

    /**
     * Revoca un permiso directo del usuario.
     */
    public function revokePermission(Permission|string $permission): self
    {
        $permName = $permission instanceof Permission ? $permission->getName() : $permission;
        $permId = $permission instanceof Permission ? $permission->getId() : null;

        $this->permissions = array_values(
            array_filter($this->permissions, function (Permission $p) use ($permId, $permName) {
                if ($p->getId() !== null && $permId !== null) {
                    return $p->getId() !== $permId;
                }
                return $p->getName() !== $permName;
            })
        );
        $this->touch();

        return $this;
    }

    /**
     * Verifica si el usuario posee un permiso directo específico.
     */
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

    /**
     * Verifica si el usuario tiene el permiso, ya sea de forma directa o a través de sus roles.
     */
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
     * Alias intuitivo de autorización (ej. $user->can('accept_sos')).
     */
    public function can(Permission|string $permission): bool
    {
        return $this->hasPermissionTo($permission);
    }

    /**
     * @return Permission[]
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }
}
