<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000013_create_vehicles_table
{
    public function up(PDO $db): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS vehicles (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL UNIQUE,
                user_id BIGINT NOT NULL,
                model_id BIGINT NOT NULL,
                plate_number VARCHAR(20) NULL UNIQUE,
                niv VARCHAR(50) NULL UNIQUE,
                engine_serial VARCHAR(50) NULL,
                year INT NOT NULL,
                color VARCHAR(50) NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'ACTIVE',
                is_verified BOOLEAN NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (model_id) REFERENCES vehicle_models(id) ON DELETE RESTRICT,
                INDEX idx_vehicle_user (user_id),
                INDEX idx_vehicle_model (model_id),
                INDEX idx_vehicle_plate (plate_number),
                INDEX idx_vehicle_niv (niv)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $db->exec($sql);
    }

    public function down(PDO $db): void
    {
        $sql = "DROP TABLE IF EXISTS vehicles;";
        $db->exec($sql);
    }
}
