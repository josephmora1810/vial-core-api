<?php

namespace App\Infrastructure\Database\Seeders;

use App\Domain\Shared\ValueObjects\Uuid;
use PDO;

class s000006_plans_seeder
{
    public function run(PDO $db): void
    {
        $plans = [
            [
                'uuid'            => Uuid::v7()->getValue(),
                'name'            => 'Plan Básico Vial 2026',
                'description'     => 'Cobertura esencial de asistencia vial en la Gran Caracas. Incluye 2 servicios de grúa al año y hasta 50 km de remolque.',
                'price_usd'       => 35.00,
                'duration_months' => 12,
                'max_services'    => 2,
                'max_km'          => 50.00,
            ],
            [
                'uuid'            => Uuid::v7()->getValue(),
                'name'            => 'Plan Plata Plus 2026',
                'description'     => 'Cobertura intermedia para conductores frecuentes. Incluye 4 servicios de grúa al año y hasta 120 km de remolque acumulado.',
                'price_usd'       => 60.00,
                'duration_months' => 12,
                'max_services'    => 4,
                'max_km'          => 120.00,
            ],
            [
                'uuid'            => Uuid::v7()->getValue(),
                'name'            => 'Plan Oro Corporativo 2026',
                'description'     => 'Máxima protección anual sin preocupaciones. Incluye 8 servicios de rescate al año y hasta 300 km de remolque en Gran Caracas y Litoral.',
                'price_usd'       => 100.00,
                'duration_months' => 12,
                'max_services'    => 8,
                'max_km'          => 300.00,
            ],
        ];

        $checkStmt = $db->prepare("SELECT id FROM plans WHERE name = :name LIMIT 1");
        $insertStmt = $db->prepare("
            INSERT INTO plans (uuid, name, description, price_usd, duration_months, max_services, max_km, is_active)
            VALUES (:uuid, :name, :description, :price_usd, :duration_months, :max_services, :max_km, 1)
        ");

        foreach ($plans as $plan) {
            $checkStmt->execute(['name' => $plan['name']]);
            if (!$checkStmt->fetchColumn()) {
                $insertStmt->execute($plan);
            }
        }

        // Asegurar permiso view_bank_balance para el rol admin
        $db->exec("
            INSERT INTO permissions (name, guard_name)
            VALUES ('view_bank_balance', 'api')
            ON DUPLICATE KEY UPDATE name = name
        ");

        $adminRoleId = $db->query("SELECT id FROM roles WHERE name = 'admin' LIMIT 1")->fetchColumn();
        $permId = $db->query("SELECT id FROM permissions WHERE name = 'view_bank_balance' LIMIT 1")->fetchColumn();

        if ($adminRoleId && $permId) {
            $attach = $db->prepare("
                INSERT INTO role_permission (role_id, permission_id)
                SELECT :r1, :p1
                WHERE NOT EXISTS (
                    SELECT 1 FROM role_permission WHERE role_id = :r2 AND permission_id = :p2
                )
            ");
            $attach->execute([
                'r1' => $adminRoleId,
                'p1' => $permId,
                'r2' => $adminRoleId,
                'p2' => $permId,
            ]);
        }
    }
}
