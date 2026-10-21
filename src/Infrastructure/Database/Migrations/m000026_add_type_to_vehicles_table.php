<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000026_add_type_to_vehicles_table
{
    public function up(PDO $db): void
    {
        $sql = "
            ALTER TABLE vehicles
            ADD COLUMN type VARCHAR(50) NOT NULL DEFAULT 'CAR' AFTER model_name,
            ADD INDEX idx_vehicle_type (type);
        ";
        $db->exec($sql);
    }

    public function down(PDO $db): void
    {
        $sql = "
            ALTER TABLE vehicles
            DROP INDEX idx_vehicle_type,
            DROP COLUMN type;
        ";
        $db->exec($sql);
    }
}
