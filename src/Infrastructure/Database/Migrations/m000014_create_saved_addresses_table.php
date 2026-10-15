<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000014_create_saved_addresses_table
{
    public function up(PDO $db): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS saved_addresses (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT NOT NULL,
                label VARCHAR(100) NOT NULL,
                address_line TEXT NOT NULL,
                latitude DECIMAL(10, 8) NOT NULL,
                longitude DECIMAL(11, 8) NOT NULL,
                is_favorite BOOLEAN NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_saved_addresses (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $db->exec($sql);
    }

    public function down(PDO $db): void
    {
        $sql = "DROP TABLE IF EXISTS saved_addresses;";
        $db->exec($sql);
    }
}
