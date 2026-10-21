<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

/**
 * Migración m000029: Creación de tablas seller_profiles y commissions
 * para el Módulo Comercial B2B de Comisiones y Billetera de Vendedores (FR-14, FR-15, FR-34).
 */
class m000029_create_seller_profiles_and_commissions_tables
{
    public function up(PDO $db): void
    {
        // 1. Tabla de Perfiles Bancarios y Comisiones de Vendedores
        $db->exec("
            CREATE TABLE IF NOT EXISTS `seller_profiles` (
                `user_id` BIGINT NOT NULL,
                `commission_rate` DECIMAL(5,2) NOT NULL DEFAULT 10.00 COMMENT 'Porcentaje de comisión acordado (ej: 15.00%)',
                `bank_id` BIGINT NULL COMMENT 'Banco nacional receptor de pagos',
                `account_number` VARCHAR(20) NULL COMMENT '20 dígitos de cuenta bancaria nacional',
                `account_type` VARCHAR(20) NOT NULL DEFAULT 'CORRIENTE' COMMENT 'CORRIENTE o AHORRO',
                `account_holder_name` VARCHAR(255) NULL COMMENT 'Nombre o Razón Social del titular de la cuenta',
                `account_holder_id` VARCHAR(20) NULL COMMENT 'C.I. o RIF del titular (ej: V-12345678 o J-12345678-0)',
                `phone_pago_movil` VARCHAR(20) NULL COMMENT 'Teléfono celular asociado a Pago Móvil',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`user_id`),
                CONSTRAINT `fk_seller_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_seller_profiles_bank` FOREIGN KEY (`bank_id`) REFERENCES `banks` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 2. Tabla de Comisiones Generadas por Venta de Contratos
        $db->exec("
            CREATE TABLE IF NOT EXISTS `commissions` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `seller_id` BIGINT NOT NULL COMMENT 'Vendedor titular acreedor de la comisión',
                `order_id` BIGINT NOT NULL COMMENT 'Orden de pago liquidada que originó la comisión',
                `amount_usd` DECIMAL(10,2) NOT NULL COMMENT 'Monto de la comisión en USD',
                `percentage` DECIMAL(5,2) NOT NULL DEFAULT 10.00 COMMENT 'Porcentaje aplicado al momento de la venta',
                `status` VARCHAR(50) NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING, WITHDRAWAL_REQUESTED, PAID, CANCELLED',
                `payment_reference` VARCHAR(100) NULL COMMENT 'Referencia bancaria de liquidación transferida por Admin',
                `paid_at` TIMESTAMP NULL COMMENT 'Fecha y hora en la que el Administrador liquidó la comisión',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY `idx_commissions_seller_status` (`seller_id`, `status`),
                KEY `idx_commissions_order_id` (`order_id`),
                CONSTRAINT `fk_commissions_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_commissions_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS `commissions`;");
        $db->exec("DROP TABLE IF EXISTS `seller_profiles`;");
    }
};
