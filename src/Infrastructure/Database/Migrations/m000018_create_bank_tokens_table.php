<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000018_create_bank_tokens_table
{
    public function up(PDO $db): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS bank_tokens (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                gateway VARCHAR(50) NOT NULL UNIQUE,
                access_token TEXT NOT NULL,
                token_type VARCHAR(50) NOT NULL DEFAULT 'Bearer',
                expires_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $db->exec($sql);
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS bank_tokens;");
    }
}
