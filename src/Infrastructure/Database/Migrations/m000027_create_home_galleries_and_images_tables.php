<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000027_create_home_galleries_and_images_tables
{
    public function up(PDO $db): void
    {
        // 1. Tabla de banners dinámicos para la Landing Page (FR-36, SRS pág. 38)
        $sqlGallery = "
            CREATE TABLE IF NOT EXISTS home_galleries (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                subtitle VARCHAR(255) NULL,
                description TEXT NULL,
                button_text VARCHAR(100) NULL,
                button_link VARCHAR(500) NULL,
                order_index INT NOT NULL DEFAULT 0,
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_gallery_order (order_index),
                INDEX idx_gallery_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $db->exec($sqlGallery);

        // 2. Tabla polimórfica de imágenes para recursos visuales en S3 (FR-36, SRS pág. 38)
        $sqlImages = "
            CREATE TABLE IF NOT EXISTS images (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                imageable_type VARCHAR(100) NOT NULL,
                imageable_id BIGINT NOT NULL,
                type VARCHAR(50) NOT NULL DEFAULT 'gallery_banner',
                path VARCHAR(2048) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_imageable (imageable_type, imageable_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $db->exec($sqlImages);
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS images;");
        $db->exec("DROP TABLE IF EXISTS home_galleries;");
    }
}
