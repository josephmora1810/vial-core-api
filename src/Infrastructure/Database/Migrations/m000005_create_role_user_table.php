<?php

namespace App\Infrastructure\Database\Migrations;


class m000005_create_role_user_table{

    public function up(\PDO $db) {
        $sql = "
            CREATE TABLE IF NOT EXISTS role_user (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                role_id BIGINT NOT NULL,
                user_id BIGINT NOT NULL,
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );
        ";
        $db->exec($sql);
    }

    public function down(\PDO $db) {
        $sql = "DROP TABLE IF EXISTS role_user;";
        $db->exec($sql);
    }
}