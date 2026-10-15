<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000011_create_vehicle_brands_table
{
    public function up(PDO $db): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS vehicle_brands (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $db->exec($sql);
    }

    public function down(PDO $db): void
    {
        $sql = "DROP TABLE IF EXISTS vehicle_brands;";
        $db->exec($sql);
    }
}
