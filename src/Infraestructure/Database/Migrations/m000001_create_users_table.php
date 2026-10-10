<?php

namespace App\Database\Migrations;

class m000001_create_users_table {

    public function up(\PDO $db) {
        $sql = "
            CREATE TABLE IF NOT EXISTS users (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NULL, -- Nullable por si usas redes sociales en el futuro
                wallet_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                consent_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL
            );
        ";
        $db->exec($sql);
    }

    public function down(\PDO $db) {
        $sql = "DROP TABLE IF EXISTS users;";
        $db->exec($sql);
    }
}
    