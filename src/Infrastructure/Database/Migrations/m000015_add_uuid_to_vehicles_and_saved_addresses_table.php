<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000015_add_uuid_to_vehicles_and_saved_addresses_table
{
    public function up(PDO $db): void
    {
        // 1. Agregar UUID a vehicles solo si no existe
        $colCheck = $db->query("SHOW COLUMNS FROM vehicles LIKE 'uuid'");
        if ($colCheck === false || $colCheck->fetch() === false) {
            $db->exec("
                ALTER TABLE vehicles 
                ADD COLUMN uuid CHAR(36) NOT NULL AFTER id,
                ADD UNIQUE INDEX uq_vehicles_uuid (uuid);
            ");
        }

        // 2. Agregar UUID a saved_addresses
        $addrCheck = $db->query("SHOW COLUMNS FROM saved_addresses LIKE 'uuid'");
        if ($addrCheck === false || $addrCheck->fetch() === false) {
            $db->exec("
                ALTER TABLE saved_addresses 
                ADD COLUMN uuid CHAR(36) NOT NULL AFTER id,
                ADD UNIQUE INDEX uq_saved_addresses_uuid (uuid);
            ");
        }
    }

    public function down(PDO $db): void
    {
        // No borrar uuid si pertenece a la definición base
        try {
            $db->exec("
                ALTER TABLE saved_addresses 
                DROP INDEX uq_saved_addresses_uuid,
                DROP COLUMN uuid;
            ");
        } catch (\Throwable) {}
    }
}
