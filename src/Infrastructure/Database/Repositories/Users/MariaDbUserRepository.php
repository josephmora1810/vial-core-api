<?php

namespace App\Infrastructure\Database\Repositories\Users;

use App\Domain\Auth\{
    Entities\Permission,
    Entities\Role,
    ValueObjects\Password
};
use App\Domain\Shared\ValueObjects\{Money, Uuid};
use App\Domain\Users\{
    Entities\User,
    Repositories\UserRepositoryInterface,
    ValueObjects\BirthDate,
    ValueObjects\Email,
    ValueObjects\Names,
    ValueObjects\NationalId,
    ValueObjects\Phone,
    ValueObjects\Surnames
};
use DateTimeImmutable;
use PDO;

class MariaDbUserRepository implements UserRepositoryInterface
{
    public function __construct(private PDO $connection)
    {
    }

    public function save(User $user): void
    {
        if ($user->getId() === null) {
            $this->insert($user);
        } else {
            $this->update($user);
        }

        $this->syncRoles($user);
        $this->syncPermissions($user);
    }

    private function insert(User $user): void
    {
        $stmt = $this->connection->prepare('
            INSERT INTO users (
                uuid, names, surnames, identification_card, birth_date,
                phone, email, password, wallet_balance, is_active,
                consent_at, created_at, updated_at, deleted_at
            ) VALUES (
                :uuid, :names, :surnames, :identification_card, :birth_date,
                :phone, :email, :password, :wallet_balance, :is_active,
                :consent_at, :created_at, :updated_at, :deleted_at
            )
        ');

        $stmt->execute([
            ':uuid' => $user->getUuid()->getValue(),
            ':names' => $user->getNames()->getValue(),
            ':surnames' => $user->getSurnames()->getValue(),
            ':identification_card' => $user->getIdentificationCard()->getValue(),
            ':birth_date' => $user->getBirthDate()?->format('Y-m-d'),
            ':phone' => $user->getPhone()->getValue(),
            ':email' => $user->getEmail()->getValue(),
            ':password' => $user->getPassword()?->getValue(),
            ':wallet_balance' => $user->getWalletBalance()->getAmount(),
            ':is_active' => $user->isActive() ? 1 : 0,
            ':consent_at' => $user->getConsentAt()?->format('Y-m-d H:i:s'),
            ':created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            ':updated_at' => $user->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ':deleted_at' => $user->getDeletedAt()?->format('Y-m-d H:i:s'),
        ]);

        $id = (int) $this->connection->lastInsertId();
        $user->setId($id);
    }

    private function update(User $user): void
    {
        $stmt = $this->connection->prepare('
            UPDATE users SET
                uuid = :uuid,
                names = :names,
                surnames = :surnames,
                identification_card = :identification_card,
                birth_date = :birth_date,
                phone = :phone,
                email = :email,
                password = :password,
                wallet_balance = :wallet_balance,
                is_active = :is_active,
                consent_at = :consent_at,
                updated_at = :updated_at,
                deleted_at = :deleted_at
            WHERE id = :id
        ');

        $stmt->execute([
            ':id' => $user->getId(),
            ':uuid' => $user->getUuid()->getValue(),
            ':names' => $user->getNames()->getValue(),
            ':surnames' => $user->getSurnames()->getValue(),
            ':identification_card' => $user->getIdentificationCard()->getValue(),
            ':birth_date' => $user->getBirthDate()?->format('Y-m-d'),
            ':phone' => $user->getPhone()->getValue(),
            ':email' => $user->getEmail()->getValue(),
            ':password' => $user->getPassword()?->getValue(),
            ':wallet_balance' => $user->getWalletBalance()->getAmount(),
            ':is_active' => $user->isActive() ? 1 : 0,
            ':consent_at' => $user->getConsentAt()?->format('Y-m-d H:i:s'),
            ':updated_at' => $user->getUpdatedAt()?->format('Y-m-d H:i:s') ?? date('Y-m-d H:i:s'),
            ':deleted_at' => $user->getDeletedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    private function syncRoles(User $user): void
    {
        $userId = $user->getId();
        if ($userId === null) {
            return;
        }

        $deleteStmt = $this->connection->prepare('DELETE FROM role_user WHERE user_id = :user_id');
        $deleteStmt->execute([':user_id' => $userId]);

        $insertStmt = $this->connection->prepare('
            INSERT INTO role_user (role_id, user_id) VALUES (:role_id, :user_id)
        ');

        $findRoleStmt = $this->connection->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');

        foreach ($user->getRoles() as $role) {
            $roleId = null;
            if ($role instanceof Role) {
                $roleId = $role->getId();
                if ($roleId === null) {
                    $findRoleStmt->execute([':name' => $role->getName()]);
                    $roleId = $findRoleStmt->fetchColumn() ?: null;
                }
            } elseif (\is_string($role)) {
                $findRoleStmt->execute([':name' => $role]);
                $roleId = $findRoleStmt->fetchColumn() ?: null;
            }

            if ($roleId !== null) {
                $insertStmt->execute([
                    ':role_id' => (int) $roleId,
                    ':user_id' => $userId,
                ]);
            }
        }
    }

    private function syncPermissions(User $user): void
    {
        $userId = $user->getId();
        if ($userId === null) {
            return;
        }

        $deleteStmt = $this->connection->prepare('DELETE FROM permission_user WHERE user_id = :user_id');
        $deleteStmt->execute([':user_id' => $userId]);

        $insertStmt = $this->connection->prepare('
            INSERT INTO permission_user (permission_id, user_id) VALUES (:permission_id, :user_id)
        ');

        foreach ($user->getPermissions() as $permission) {
            if ($permission instanceof Permission && $permission->getId() !== null) {
                $insertStmt->execute([
                    ':permission_id' => $permission->getId(),
                    ':user_id' => $userId,
                ]);
            }
        }
    }

    public function findByEmail(Email $email): ?User
    {
        $stmt = $this->connection->prepare('
            SELECT * FROM users
            WHERE email = :email AND deleted_at IS NULL
            LIMIT 1
        ');
        $stmt->execute([':email' => $email->getValue()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrateUser($row);
    }

    public function findByUuid(Uuid $uuid): ?User
    {
        $stmt = $this->connection->prepare('
            SELECT * FROM users
            WHERE uuid = :uuid AND deleted_at IS NULL
            LIMIT 1
        ');
        $stmt->execute([':uuid' => $uuid->getValue()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrateUser($row);
    }

    public function findByIdentificationCard(NationalId $nationalId): ?User
    {
        $stmt = $this->connection->prepare('
            SELECT * FROM users
            WHERE identification_card = :id_card AND deleted_at IS NULL
            LIMIT 1
        ');
        $stmt->execute([':id_card' => $nationalId->getValue()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrateUser($row);
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->connection->prepare('
            SELECT * FROM users
            WHERE id = :id AND deleted_at IS NULL
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrateUser($row);
    }

    private function loadRoles(int $userId): array
    {
        $stmt = $this->connection->prepare('
            SELECT r.id AS role_id, r.name AS role_name, r.guard_name AS role_guard,
                   p.id AS perm_id, p.name AS perm_name, p.guard_name AS perm_guard
            FROM roles r
            INNER JOIN role_user ru ON r.id = ru.role_id
            LEFT JOIN role_permission rp ON r.id = rp.role_id
            LEFT JOIN permissions p ON rp.permission_id = p.id
            WHERE ru.user_id = :user_id
        ');
        $stmt->execute([':user_id' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $rolesById = [];
        foreach ($rows as $row) {
            $roleId = (int) $row['role_id'];
            if (!isset($rolesById[$roleId])) {
                $rolesById[$roleId] = [
                    'id'          => $roleId,
                    'name'        => $row['role_name'],
                    'guard_name'  => $row['role_guard'],
                    'permissions' => []
                ];
            }

            if (!empty($row['perm_id'])) {
                $rolesById[$roleId]['permissions'][] = new Permission(
                    id: (int) $row['perm_id'],
                    name: $row['perm_name'],
                    guardName: $row['perm_guard']
                );
            }
        }

        $roles = [];
        foreach ($rolesById as $data) {
            $roles[] = new Role(
                id: $data['id'],
                name: $data['name'],
                guardName: $data['guard_name'],
                permissions: $data['permissions']
            );
        }

        return $roles;
    }

    private function loadPermissions(int $userId): array
    {
        $stmt = $this->connection->prepare('
            SELECT p.id, p.name, p.guard_name
            FROM permissions p
            INNER JOIN permission_user pu ON p.id = pu.permission_id
            WHERE pu.user_id = :user_id
        ');
        $stmt->execute([':user_id' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $permissions = [];
        foreach ($rows as $row) {
            $permissions[] = new Permission(
                id: (int) $row['id'],
                name: $row['name'],
                guardName: $row['guard_name']
            );
        }

        return $permissions;
    }

    private function hydrateUser(array $row): User
    {
        $userId = (int) $row['id'];
        $roles = $this->loadRoles($userId);
        $permissions = $this->loadPermissions($userId);

        return new User(
            id: $userId,
            uuid: new Uuid($row['uuid']),
            names: new Names($row['names']),
            surnames: new Surnames($row['surnames']),
            email: new Email($row['email']),
            password: $row['password'] !== null ? Password::fromHash($row['password']) : null,
            phone: new Phone($row['phone']),
            walletBalance: new Money((float) $row['wallet_balance'], 'VES'),
            identificationCard: new NationalId($row['identification_card']),
            birthDate: !empty($row['birth_date']) ? new BirthDate($row['birth_date']) : null,
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: !empty($row['updated_at']) ? new DateTimeImmutable($row['updated_at']) : null,
            consentAt: !empty($row['consent_at']) ? new DateTimeImmutable($row['consent_at']) : null,
            deletedAt: !empty($row['deleted_at']) ? new DateTimeImmutable($row['deleted_at']) : null,
            isActive: (bool) $row['is_active'],
            roles: $roles,
            permissions: $permissions
        );
    }
}