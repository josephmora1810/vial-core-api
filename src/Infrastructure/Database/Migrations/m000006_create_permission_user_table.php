<?php

namespace App\Infrastructure\Database\Migrations;


class m000006_create_permission_user_table{

    public function up(\PDO $db) {
        $sql = "
            CREATE TABLE IF NOT EXISTS permission_user (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT NOT NULL,
                permission_id BIGINT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
            );
        ";
        $db->exec($sql);
    }

    public function down(\PDO $db) {
        $sql = "DROP TABLE IF EXISTS permission_user;";
        $db->exec($sql);
    }
}