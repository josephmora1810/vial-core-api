<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000008_create_documents_table
{
    public function up(PDO $db): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS documents (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                
                -- Relación Polimórfica (ej: documentable_type = 'user', documentable_id = 1)
                -- Permite vincular documentos a usuarios, vehículos o grúas
                documentable_type VARCHAR(100) NOT NULL,
                documentable_id BIGINT NOT NULL,

                -- Tipo de documento: identity_card, driving_license, vehicle_registration
                document_type VARCHAR(50) NOT NULL,

                -- Ruta temporal o URL final en Amazon S3
                -- NOTA S3: Al inicio se guarda la ruta local temporal. Más adelante un Job
                -- en segundo plano (Redis Queue) sube el archivo a S3 y actualiza este campo.
                file_path VARCHAR(2048) NOT NULL,

                -- JSON completo con los datos extraídos por el OCR para auditoría (FR-33)
                extracted_data JSON NULL,

                -- Estado KYC: PENDING, VERIFIED, REJECTED (FR-10)
                status VARCHAR(50) NOT NULL DEFAULT 'PENDING',

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                -- Índice compuesto para búsquedas ultra rápidas por entidad
                INDEX idx_documentable (documentable_type, documentable_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        $db->exec($sql);
    }

    public function down(PDO $db): void
    {
        $sql = "DROP TABLE IF EXISTS documents;";
        $db->exec($sql);
    }
}