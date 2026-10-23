<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000035_create_driver_shifts_table
{
    /**
     * Tabla para gestionar los turnos de trabajo y la grúa activa en operación de cada gruero.
     * Regla de Negocio: Un gruero solo puede tener una única grúa activa a la vez durante su turno.
     * No puede cambiar de grúa ni finalizar turno si se encuentra en un servicio activo (IN_ROUTE o IN_PROGRESS).
     */
    public function up(PDO $db): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS driver_shifts (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                driver_id BIGINT NOT NULL COMMENT 'ID del usuario con rol driver',
                crane_id BIGINT NOT NULL COMMENT 'ID del vehículo tipo CRANE en operación',
                is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Indica si el turno está abierto y en guardia',
                started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ended_at TIMESTAMP NULL,
                last_latitude DECIMAL(10, 8) NULL COMMENT 'Última latitud reportada en telemetría de radar',
                last_longitude DECIMAL(11, 8) NULL COMMENT 'Última longitud reportada en telemetría de radar',
                last_ping_at TIMESTAMP NULL COMMENT 'Marca de tiempo del último latido de geolocalización',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_driver_shifts_driver (driver_id),
                INDEX idx_driver_shifts_crane (crane_id),
                INDEX idx_driver_shifts_active (is_active),
                CONSTRAINT fk_driver_shifts_driver FOREIGN KEY (driver_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT fk_driver_shifts_crane FOREIGN KEY (crane_id) REFERENCES vehicles (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $db->exec($sql);
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS driver_shifts;");
    }
}
