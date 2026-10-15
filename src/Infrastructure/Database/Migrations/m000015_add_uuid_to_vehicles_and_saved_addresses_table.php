<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000015_add_uuid_to_vehicles_and_saved_addresses_table
{
    public function up(PDO $db): void
    {
        // 1. Agregar UUID a vehicles
        $db->exec("
            ALTER TABLE vehicles 
            ADD COLUMN uuid CHAR(36) NOT NULL AFTER id,
            ADD UNIQUE INDEX uq_vehicles_uuid (uuid);
        ");

        // 2. Agregar UUID a saved_addresses
        $db->exec("
            ALTER TABLE saved_addresses 
            ADD COLUMN uuid CHAR(36) NOT NULL AFTER id,
            ADD UNIQUE INDEX uq_saved_addresses_uuid (uuid);
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE vehicles 
            DROP INDEX uq_vehicles_uuid,
            DROP COLUMN uuid;
        ");

        $db->exec("
            ALTER TABLE saved_addresses 
            DROP INDEX uq_saved_addresses_uuid,
            DROP COLUMN uuid;
        ");
    }
}
