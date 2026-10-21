<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000023_create_payments_table
{
    public function up(PDO $db): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS payments (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL UNIQUE,
                order_id BIGINT NOT NULL,
                paid_by_user_id BIGINT NOT NULL,
                amount DECIMAL(12, 2) NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT 'VES',
                payment_method VARCHAR(50) NOT NULL,
                commerce VARCHAR(100) NOT NULL DEFAULT 'Bancaribe',
                transaction_id VARCHAR(100) NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'pending',
                request_payload JSON NULL,
                response_payload JSON NULL,
                error_message VARCHAR(255) NULL,
                paid_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                FOREIGN KEY (paid_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_payment_order (order_id),
                INDEX idx_payment_tx (transaction_id),
                INDEX idx_payment_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $db->exec($sql);
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS payments;");
    }
}
