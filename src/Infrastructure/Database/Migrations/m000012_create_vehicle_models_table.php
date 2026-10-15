<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000012_create_vehicle_models_table
{
    public function up(PDO $db): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS vehicle_models (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                brand_id INT NOT NULL,
                vehicle_class_id INT NULL,
                name VARCHAR(150) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (brand_id) REFERENCES vehicle_brands(id) ON DELETE CASCADE,
                FOREIGN KEY (vehicle_class_id) REFERENCES vehicle_classes(id) ON DELETE SET NULL,
                UNIQUE KEY uq_brand_model (brand_id, name),
                INDEX idx_brand_id (brand_id),
                INDEX idx_vehicle_class_id (vehicle_class_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $db->exec($sql);
    }

    public function down(PDO $db): void
    {
        $sql = "DROP TABLE IF EXISTS vehicle_models;";
        $db->exec($sql);
    }
}
