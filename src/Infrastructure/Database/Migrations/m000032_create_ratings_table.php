<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000032_create_ratings_table
{
    /**
     * IMPORTANTE: como nota de arquitectura, 
     * esta tabla de calificaciones se creó para almacenar las reseñas 
     * y puntuaciones del servicio de grúa (cliente a gruero, gruero a cliente), 
     * con el fin de calcular el promedio del chofer y control de calidad (FR-27).
     */
    public function up(PDO $db): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS ratings (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                tow_service_id BIGINT NOT NULL COMMENT 'ID del servicio de grúa asociado',
                user_id BIGINT NOT NULL COMMENT 'ID del usuario que realiza la calificación',
                rated_user_id BIGINT NOT NULL COMMENT 'ID del usuario que recibe la calificación',
                rating TINYINT NOT NULL COMMENT 'Calificación del 1 al 5',
                comment TEXT NULL COMMENT 'Comentario opcional del usuario',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                UNIQUE KEY uq_rating_service_user (tow_service_id, user_id),
                CONSTRAINT fk_ratings_service FOREIGN KEY (tow_service_id) REFERENCES tow_services (id) ON DELETE CASCADE,
                CONSTRAINT fk_ratings_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT fk_ratings_rated_user FOREIGN KEY (rated_user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $db->exec($sql);
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS ratings;");
    }
}
