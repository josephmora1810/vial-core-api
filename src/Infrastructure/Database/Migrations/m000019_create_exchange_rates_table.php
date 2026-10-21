<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000019_create_exchange_rates_table
{
    public function up(PDO $db): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS exchange_rates (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                currency CHAR(3) NOT NULL,
                type VARCHAR(20) NOT NULL,
                rate DECIMAL(15, 6) NOT NULL,
                date DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_exchange_rate_day (currency, type, date),
                INDEX idx_exchange_lookup (currency, type, date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $db->exec($sql);
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS exchange_rates;");
    }
}
