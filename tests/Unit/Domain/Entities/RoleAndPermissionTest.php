<?php

namespace Tests\Unit\Domain\Entities;

use App\Domain\Auth\Entities\{
    Concerns\HasRolesAndPermissions,
    Permission,
    Role
};
use PHPUnit\Framework\TestCase;

class RoleAndPermissionTest extends TestCase
{
    public function testRoleEntityBasics(): void
    {
        $role = new Role(1, 'driver', 'api');

        $this->assertSame(1, $role->getId());
        $this->assertSame('driver', $role->getName());
        $this->assertSame('driver', $role->value());
        $this->assertSame('driver', (string)$role);
        $this->assertSame('api', $role->getGuardName());
        $this->assertEmpty($role->getPermissions());
    }

    public function testRolePermissionsAssignmentAndCheck(): void
    {
        $role = new Role(1, 'admin');
        $perm1 = new Permission(1, 'manage_users');
        $perm2 = new Permission(2, 'view_reports');

        $role->givePermissionTo($perm1);
        $role->givePermissionTo($perm2);

        $this->assertCount(2, $role->getPermissions());
        $this->assertTrue($role->hasPermissionTo('manage_users'));
        $this->assertTrue($role->hasPermissionTo($perm2));
        $this->assertFalse($role->hasPermissionTo('delete_database'));
    }

    public function testPermissionEntityBasics(): void
    {
        $permission = new Permission(10, 'export_csv', 'web');

        $this->assertSame(10, $permission->getId());
        $this->assertSame('export_csv', $permission->getName());
        $this->assertSame('web', $permission->getGuardName());

        $role = new Role(2, 'seller');
        $permission->assignToRole($role);
        $this->addToAssertionCount(1);
    }

    public function testHasRolesAndPermissionsTrait(): void
    {
        $entity = new class {
            use HasRolesAndPermissions;

            public array $roles = [];
            public array $permissions = [];

            public function touch(): void
            {
                // mock touch
            }
        };

        $roleClient = new Role(1, 'client');
        $roleDriver = new Role(2, 'driver');
        $permSos = new Permission(1, 'request_sos');
        $permTow = new Permission(2, 'accept_tow');

        // Asignar rol
        $entity->assignToRole($roleClient);
        $this->assertTrue($entity->hasRole('client'));
        $this->assertTrue($entity->hasRole($roleClient));
        $this->assertFalse($entity->hasRole('driver'));
        $this->assertSame($roleClient, $entity->getRole());

        // Prevenir duplicados
        $entity->assignRole($roleClient);
        $this->assertCount(1, $entity->getRoleNames());

        // Asignar segundo rol
        $entity->assignRole($roleDriver);
        $this->assertTrue($entity->hasAnyRole(['client', 'admin']));
        $this->assertFalse($entity->hasAnyRole(['admin', 'seller']));
        $this->assertCount(2, $entity->getRoles());

        // Permisos directos
        $entity->givePermissionTo($permSos);
        $this->assertTrue($entity->hasDirectPermission('request_sos'));
        $this->assertTrue($entity->hasPermissionTo('request_sos'));
        $this->assertTrue($entity->can('request_sos'));
        $this->assertFalse($entity->hasPermissionTo('accept_tow'));

        // Permiso a través de rol
        $roleDriver->givePermissionTo($permTow);
        $this->assertTrue($entity->hasPermissionTo('accept_tow'));
        $this->assertTrue($entity->can('accept_tow'));

        // Revocar permiso directo
        $entity->revokePermission('request_sos');
        $this->assertFalse($entity->hasDirectPermission('request_sos'));

        // Remover rol
        $entity->removeRole('driver');
        $this->assertFalse($entity->hasRole('driver'));
        $this->assertFalse($entity->hasPermissionTo('accept_tow'));
    }
}
