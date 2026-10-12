<?php

namespace App\Infrastructure\Database\Migrations;


class m000004_create_role_permission_table {

    public function up(\PDO $db) {
        $sql = "
            CREATE TABLE IF NOT EXISTS role_permission (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                role_id BIGINT NOT NULL,
                permission_id BIGINT NOT NULL,
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
                FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
            );
        ";
        $db->exec($sql);
    }

    public function down(\PDO $db) {
        $sql = "DROP TABLE IF EXISTS role_permission;";
        $db->exec($sql);
    }
}