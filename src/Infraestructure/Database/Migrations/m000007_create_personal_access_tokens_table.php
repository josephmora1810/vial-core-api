<?php

namespace App\Database\Migrations;


class m000007_create_personal_access_tokens_table{

    public function up(\PDO $db) {
        $sql = "
            CREATE TABLE IF NOT EXISTS personal_access_tokens (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE, -- Un hash SHA-256 del refresh token
                expires_at TIMESTAMP NOT NULL,
                is_revoked BOOLEAN NOT NULL DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );
        ";
        $db->exec($sql);
    }

    public function down(\PDO $db) {
        $sql = "DROP TABLE IF EXISTS personal_access_tokens;";
        $db->exec($sql);
    }
}