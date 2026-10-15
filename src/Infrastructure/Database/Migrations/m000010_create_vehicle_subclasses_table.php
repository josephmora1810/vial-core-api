<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000010_create_vehicle_subclasses_table
{
    public function up(PDO $db): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS vehicle_subclasses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                vehicle_class_id INT NULL,
                name VARCHAR(100) NOT NULL UNIQUE,
                description VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (vehicle_class_id) REFERENCES vehicle_classes(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $db->exec($sql);
    }

    public function down(PDO $db): void
    {
        $sql = "DROP TABLE IF EXISTS vehicle_subclasses;";
        $db->exec($sql);
    }
}
