<?php

namespace App\Database\Migrations;


class m000002_create_roles_table {

    public function up(\PDO $db) {
        $sql = "
            CREATE TABLE IF NOT EXISTS roles (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL UNIQUE,
                guard_name VARCHAR(255) NOT NULL DEFAULT 'api'
            );
        ";
        $db->exec($sql);
    }

    public function down(\PDO $db) {
        $sql = "DROP TABLE IF EXISTS roles;";
        $db->exec($sql);
    }
}