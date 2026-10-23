<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000033_add_subclass_and_type_to_vehicle_models_table
{
    public function up(PDO $db): void
    {
        // En la arquitectura 3NF, vehicle_subclass_id y type ya se crean directamente en m000012.
        // Se preserva esta migración como no-op para mantener la secuencia histórica de steps.
    }

    public function down(PDO $db): void
    {
    }
}
