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
                vehicle_subclass_id INT NOT NULL,
                type VARCHAR(50) NOT NULL DEFAULT 'CAR',
                name VARCHAR(150) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (brand_id) REFERENCES vehicle_brands(id) ON DELETE CASCADE,
                FOREIGN KEY (vehicle_subclass_id) REFERENCES vehicle_subclasses(id) ON DELETE RESTRICT,
                UNIQUE KEY uq_brand_model (brand_id, name),
                INDEX idx_brand_id (brand_id),
                INDEX idx_vehicle_subclass_id (vehicle_subclass_id),
                INDEX idx_vehicle_models_type (type)
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
