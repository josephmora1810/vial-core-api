<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000009_create_vehicle_classes_table
{
    public function up(PDO $db): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS vehicle_classes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) NOT NULL UNIQUE,
                name VARCHAR(100) NOT NULL UNIQUE,
                description VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $db->exec($sql);
    }

    public function down(PDO $db): void
    {
        $sql = "DROP TABLE IF EXISTS vehicle_classes;";
        $db->exec($sql);
    }
}
