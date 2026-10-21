<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000028_add_verification_code_and_integrity_hash_to_service_contracts_table
{
    public function up(PDO $db): void
    {
        // 1. Agregar columnas de seguridad antifraude y verificación en service_contracts
        $sql = "
            ALTER TABLE service_contracts
            ADD COLUMN IF NOT EXISTS verification_code VARCHAR(32) NULL AFTER seller_id,
            ADD COLUMN IF NOT EXISTS integrity_hash VARCHAR(64) NULL AFTER verification_code,
            ADD UNIQUE KEY IF NOT EXISTS uq_contracts_verification_code (verification_code);
        ";
        $db->exec($sql);
    }

    public function down(PDO $db): void
    {
        $sql = "
            ALTER TABLE service_contracts
            DROP INDEX IF EXISTS uq_contracts_verification_code,
            DROP COLUMN IF EXISTS integrity_hash,
            DROP COLUMN IF EXISTS verification_code;
        ";
        $db->exec($sql);
    }
}
