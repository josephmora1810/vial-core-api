<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000030_create_tow_services_table
{
    public function up(PDO $db): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS tow_services (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL UNIQUE,
                client_id BIGINT NOT NULL COMMENT 'ID del cliente que solicita el servicio',
                driver_id BIGINT NULL COMMENT 'Nulo hasta que un conductor acepte el servicio',
                vehicle_id BIGINT NOT NULL COMMENT 'ID del vehículo averiado',
                contract_id BIGINT NULL COMMENT 'ID del contrato asociado',

                origin_lat DECIMAL(10, 8) NOT NULL,
                origin_lng DECIMAL(11, 8) NOT NULL,
                origin_address VARCHAR(255) NOT NULL,
                dest_lat DECIMAL(10, 8) NOT NULL,
                dest_lng DECIMAL(11, 8) NOT NULL,
                dest_address VARCHAR(255) NOT NULL,
                saved_address_id BIGINT NULL COMMENT 'FK a saved_addresses.id si el cliente seleccionó una dirección guardada',
                
                failure_description TEXT NOT NULL,
                estimated_distance_km DECIMAL(6, 2) NOT NULL,
                extra_km DECIMAL(6, 2) NOT NULL DEFAULT 0.00,
                extra_order_id BIGINT NULL COMMENT 'FK a orders.id para cobrar el excedente de km',
                status VARCHAR(50) NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING, EN_ROUTE, IN_PROGRESS, COMPLETED, CANCELLED_BY_CLIENT, CANCELLED_BY_DRIVER, CANCELLED_BY_TIMEOUT',
                cancel_reason VARCHAR(255) NULL COMMENT 'Motivo de la cancelación',
                cancelled_by ENUM('CLIENT', 'DRIVER', 'SYSTEM_TIMEOUT') NULL COMMENT 'Quién canceló el servicio',
                
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                accepted_at TIMESTAMP NULL COMMENT 'Fecha y hora en que el conductor aceptó el servicio',
                arrived_at TIMESTAMP NULL COMMENT 'Fecha y hora en que el conductor llegó al lugar del cliente',
                completed_at TIMESTAMP NULL COMMENT 'Fecha y hora en que el servicio fue completado',
                cancelled_at TIMESTAMP NULL COMMENT 'Fecha y hora en que el servicio fue cancelado',
            
                INDEX idx_tow_services_status (status),
                INDEX idx_tow_services_client (client_id),
                INDEX idx_tow_services_driver (driver_id),
                CONSTRAINT fk_tow_services_client FOREIGN KEY (client_id) REFERENCES users (id) ON DELETE RESTRICT,
                CONSTRAINT fk_tow_services_driver FOREIGN KEY (driver_id) REFERENCES users (id) ON DELETE SET NULL,
                CONSTRAINT fk_tow_services_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE RESTRICT,
                CONSTRAINT fk_tow_services_contract FOREIGN KEY (contract_id) REFERENCES service_contracts (id) ON DELETE SET NULL,
                CONSTRAINT fk_tow_services_saved_address FOREIGN KEY (saved_address_id) REFERENCES saved_addresses (id) ON DELETE SET NULL,
                CONSTRAINT fk_tow_services_order FOREIGN KEY (extra_order_id) REFERENCES orders (id) ON DELETE SET NULL

            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $db->exec($sql);
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS tow_services;");
    }
}
