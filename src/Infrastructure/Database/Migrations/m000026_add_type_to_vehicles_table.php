<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000026_add_type_to_vehicles_table
{
    public function up(PDO $db): void
    {
        // En la arquitectura 3NF, 'type' reside exclusivamente en vehicle_models.
        // Se preserva esta migración como no-op para mantener la secuencia y compatibilidad de steps.
    }

    public function down(PDO $db): void
    {
    }
}
