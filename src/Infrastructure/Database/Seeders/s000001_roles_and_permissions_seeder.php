<?php

namespace App\Infrastructure\Database\Seeders;

use App\Domain\Enums\UserRole;
use PDO;

class s000001_roles_and_permissions_seeder
{
    public function run(PDO $db): void
    {
        // 1. Roles basados en el Enum oficial UserRole
        $roles = UserRole::values(); // ['admin', 'driver', 'client', 'seller']

        $insertRoleStmt = $db->prepare("
            INSERT INTO roles (name, guard_name) 
            VALUES (:name, 'api')
            ON DUPLICATE KEY UPDATE name = name
        ");

        foreach ($roles as $roleName) {
            $insertRoleStmt->execute(['name' => $roleName]);
        }

        // Obtener IDs de los roles
        $stmt = $db->query("SELECT id, name FROM roles");
        $roleMap = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $roleMap[$row['name']] = (int)$row['id'];
        }

        // 2. Permisos del Sistema
        $permissions = [
            // Cliente
            'request_sos'            => 'Permite solicitar auxilio vial / grúa en tiempo real',
            'cancel_sos'             => 'Permite cancelar una solicitud de grúa en curso',
            'view_sos'               => 'Permite ver el estado y la telemetría de la grúa asignada',
            'register_vehicle'       => 'Permite registrar y gestionar vehículos propios',
            'view_vehicle'           => 'Permite consultar el catálogo y detalle de vehículos propios',
            'manage_addresses'       => 'Permite registrar y consultar ubicaciones y coordenadas favoritas',
            'buy_service_contract'   => 'Permite comprar y afiliar contratos de asistencia vial',

            // Gruero / Conductor
            'accept_sos'             => 'Permite a los grueros recibir y aceptar servicios de remolque',
            'complete_sos'           => 'Permite finalizar un servicio de grúa y registrar odómetro',
            'manage_fleet'           => 'Permite registrar vehículos de grúa y documentos de flota',

            // Vendedor
            'sell_service_contract'  => 'Permite afiliar a terceros y emitir links de cobro de contratos',
            'withdraw_commissions'   => 'Permite solicitar la liquidación de comisiones ganadas',

            // Administrador
            'manage_users'           => 'Permite listar, suspender y reactivar cuentas de usuario',
            'audit_kyc'              => 'Permite auditar extracciones OCR y aprobar/rechazar documentos KYC',
            'manage_catalog'         => 'Permite crear o modificar planes de servicio y clases de vehículos',
            'view_reports'           => 'Permite descargar reportes consolidados financieros y de servicio',
        ];

        $insertPermStmt = $db->prepare("
            INSERT INTO permissions (name, guard_name) 
            VALUES (:name, 'api')
            ON DUPLICATE KEY UPDATE name = name
        ");

        foreach (array_keys($permissions) as $permName) {
            $insertPermStmt->execute(['name' => $permName]);
        }

        // Obtener IDs de los permisos
        $stmt = $db->query("SELECT id, name FROM permissions");
        $permMap = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $permMap[$row['name']] = (int)$row['id'];
        }

        // 3. Matriz de Roles y Permisos
        $rolePermissions = [
            'client' => [
                'request_sos',
                'cancel_sos',
                'view_sos',
                'register_vehicle',
                'view_vehicle',
                'manage_addresses',
                'buy_service_contract',
            ],
            'driver' => [
                'accept_sos',
                'cancel_sos',
                'view_sos',
                'complete_sos',
                'manage_fleet',
            ],
            'seller' => [
                'sell_service_contract',
                'withdraw_commissions',
                'view_vehicle',
            ],
            'admin' => array_keys($permissions), // El administrador tiene todos los permisos
        ];

        $attachStmt = $db->prepare("
            INSERT INTO role_permission (role_id, permission_id) 
            SELECT :role_id, :permission_id
            WHERE NOT EXISTS (
                SELECT 1 FROM role_permission 
                WHERE role_id = :role_id2 AND permission_id = :permission_id2
            )
        ");

        foreach ($rolePermissions as $roleName => $perms) {
            if (!isset($roleMap[$roleName])) {
                continue;
            }
            $roleId = $roleMap[$roleName];

            foreach ($perms as $permName) {
                if (!isset($permMap[$permName])) {
                    continue;
                }
                $permId = $permMap[$permName];

                $attachStmt->execute([
                    'role_id'         => $roleId,
                    'permission_id'   => $permId,
                    'role_id2'        => $roleId,
                    'permission_id2'  => $permId,
                ]);
            }
        }
    }
}
