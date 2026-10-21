<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000021_create_service_contracts_table
{
    public function up(PDO $db): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS service_contracts (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL UNIQUE,
                client_id BIGINT NOT NULL,
                plan_id BIGINT NOT NULL,
                vehicle_id BIGINT NULL,
                remaining_services INT NOT NULL DEFAULT 0,
                remaining_km DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                expires_at DATE NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'PENDING_PAYMENT',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE RESTRICT,
                FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
                INDEX idx_contract_client (client_id),
                INDEX idx_contract_vehicle (vehicle_id),
                INDEX idx_contract_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $db->exec($sql);
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS service_contracts;");
    }
}
