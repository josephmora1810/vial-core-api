<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000025_add_seller_id_to_service_contracts_table
{
    public function up(PDO $db): void
    {
        $stmt = $db->query("SHOW COLUMNS FROM service_contracts LIKE 'seller_id'");
        if ($stmt && $stmt->fetch() === false) {
            $db->exec("
                ALTER TABLE service_contracts
                ADD COLUMN seller_id BIGINT NULL AFTER client_id,
                ADD CONSTRAINT fk_contract_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL,
                ADD INDEX idx_contract_seller (seller_id);
            ");
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE service_contracts
            DROP FOREIGN KEY fk_contract_seller,
            DROP INDEX idx_contract_seller,
            DROP COLUMN seller_id;
        ");
    }
}
