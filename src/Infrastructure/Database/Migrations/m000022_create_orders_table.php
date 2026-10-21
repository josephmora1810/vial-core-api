<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000022_create_orders_table
{
    public function up(PDO $db): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS orders (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL UNIQUE,
                order_number VARCHAR(50) NOT NULL UNIQUE,
                user_id BIGINT NOT NULL,
                orderable_type VARCHAR(100) NOT NULL,
                orderable_id BIGINT NOT NULL,
                amount_usd DECIMAL(10, 2) NOT NULL,
                exchange_rate DECIMAL(15, 6) NOT NULL,
                amount_ves DECIMAL(12, 2) NOT NULL,
                bancaribe_ref VARCHAR(100) NULL UNIQUE,
                status VARCHAR(50) NOT NULL DEFAULT 'PENDING',
                paid_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_order_user (user_id),
                INDEX idx_order_orderable (orderable_type, orderable_id),
                INDEX idx_order_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $db->exec($sql);
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS orders;");
    }
}
