<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000031_create_tow_service_logs_table
{
    /**
     * IMPORTANTE: como nota de arquitectura, 
     * esta tabla de logs se creó para almacenar la ubicación y 
     * velocidad del vehículo de grúa durante el servicio, 
     * con el fin de poder mostrar en tiempo real la ubicación del vehículo al cliente y al conductor. 
     * Esto es útil para mejorar la experiencia del usuario y para fines de auditoría y control de calidad.
     */
    public function up(PDO $db): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS tow_service_logs (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                tow_service_id BIGINT NOT NULL COMMENT 'ID del servicio de grúa asociado',
                lat DECIMAL(10, 8) NOT NULL,
                lng DECIMAL(11, 8) NOT NULL,
                speed_kmh DECIMAL(6, 2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                INDEX idx_tow_service_logs_service (tow_service_id),
                CONSTRAINT fk_tow_service_logs_service FOREIGN KEY (tow_service_id) REFERENCES tow_services (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $db->exec($sql);
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS tow_service_logs;");
    }
}
