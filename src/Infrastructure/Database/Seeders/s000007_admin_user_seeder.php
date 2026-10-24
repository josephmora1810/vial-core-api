<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Seeders;

use App\Domain\Shared\ValueObjects\Uuid;
use PDO;

class s000007_admin_user_seeder
{
    public function run(PDO $db): void
    {
        $email = 'sistemabenefit@gmail.com';
        $plainPassword = 'YeDDyp3G';
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

        // 1. Obtener o crear el rol admin
        $stmt = $db->prepare("SELECT id FROM roles WHERE name = 'admin' LIMIT 1");
        $stmt->execute();
        $adminRole = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$adminRole) {
            $db->exec("INSERT INTO roles (name, guard_name) VALUES ('admin', 'api')");
            $adminRoleId = (int)$db->lastInsertId();
        } else {
            $adminRoleId = (int)$adminRole['id'];
        }

        // 2. Verificar si el usuario ya existe por email
        $checkStmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $checkStmt->execute(['email' => $email]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $userId = (int)$existing['id'];
            // Actualizar contraseña y estatus
            $updateStmt = $db->prepare("
                UPDATE users 
                SET password = :password, is_active = 1, deleted_at = NULL 
                WHERE id = :id
            ");
            $updateStmt->execute([
                'password' => $hashedPassword,
                'id'       => $userId,
            ]);
            echo "Usuario administrador {$email} actualizado con la nueva contraseña.\n";
        } else {
            // Generar una cédula válida única que no colisione
            $idNumber = 10000001;
            while (true) {
                $candidateCi = "V-{$idNumber}";
                $ciCheck = $db->prepare("SELECT 1 FROM users WHERE identification_card = :ci LIMIT 1");
                $ciCheck->execute(['ci' => $candidateCi]);
                if (!$ciCheck->fetch()) {
                    break;
                }
                $idNumber++;
            }

            $uuid = Uuid::generate()->getValue();
            $insertStmt = $db->prepare("
                INSERT INTO users (
                    uuid, names, surnames, identification_card, birth_date, phone, 
                    email, password, wallet_balance, is_active, consent_at, created_at, updated_at
                ) VALUES (
                    :uuid, :names, :surnames, :identification_card, :birth_date, :phone, 
                    :email, :password, 0.00, 1, NOW(), NOW(), NOW()
                )
            ");
            $insertStmt->execute([
                'uuid'                => $uuid,
                'names'               => 'Administrador',
                'surnames'            => 'Benefit',
                'identification_card' => $candidateCi,
                'birth_date'          => '1990-01-01',
                'phone'               => '04140000000',
                'email'               => $email,
                'password'            => $hashedPassword,
            ]);
            $userId = (int)$db->lastInsertId();
            echo "Usuario administrador {$email} creado exitosamente (ID: {$userId}, CI: {$candidateCi}).\n";
        }

        // 3. Asignar rol admin en role_user
        $assignStmt = $db->prepare("
            INSERT INTO role_user (role_id, user_id)
            SELECT :role_id, :user_id
            WHERE NOT EXISTS (
                SELECT 1 FROM role_user WHERE role_id = :role_id_check AND user_id = :user_id_check
            )
        ");
        $assignStmt->execute([
            'role_id'       => $adminRoleId,
            'user_id'       => $userId,
            'role_id_check' => $adminRoleId,
            'user_id_check' => $userId,
        ]);

        echo "Rol de Administrador vinculado al usuario {$email}.\n";
    }
}
