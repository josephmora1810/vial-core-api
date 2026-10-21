<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000024_change_service_contracts_dates_to_timestamp
{
    public function up(PDO $db): void
    {
        // Agregar starts_at DATETIME NULL si no existe y cambiar expires_at de DATE a DATETIME NULL
        // para almacenar fecha, hora, minuto y segundo exactos del momento del pago hasta el año siguiente
        $checkStartsAt = $db->query("SHOW COLUMNS FROM service_contracts LIKE 'starts_at'")->fetch();
        if (!$checkStartsAt) {
            $db->exec("ALTER TABLE service_contracts ADD COLUMN starts_at DATETIME NULL AFTER remaining_km;");
        }

        $db->exec("ALTER TABLE service_contracts MODIFY COLUMN expires_at DATETIME NULL;");
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE service_contracts MODIFY COLUMN expires_at DATE NULL;");
        $checkStartsAt = $db->query("SHOW COLUMNS FROM service_contracts LIKE 'starts_at'")->fetch();
        if ($checkStartsAt) {
            $db->exec("ALTER TABLE service_contracts DROP COLUMN starts_at;");
        }
    }
}
