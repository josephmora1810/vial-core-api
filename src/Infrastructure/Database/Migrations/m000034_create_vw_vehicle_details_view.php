<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000034_create_vw_vehicle_details_view
{
    public function up(PDO $db): void
    {
        $sql = "
            CREATE OR REPLACE VIEW vw_vehicle_details AS
            SELECT 
                v.id,
                v.uuid,
                v.user_id,
                v.model_id,
                v.plate_number,
                v.niv,
                v.engine_serial,
                v.year,
                v.color,
                v.status AS vehicle_status,
                v.is_verified,
                v.created_at,
                v.updated_at,

                -- Propietario
                u.names AS owner_names,
                u.surnames AS owner_surnames,
                u.identification_card AS owner_national_id,
                u.phone AS owner_phone,
                u.email AS owner_email,

                -- Cadena 3NF Normalizada:
                -- vehicles -> vehicle_models -> vehicle_brands & vehicle_subclasses -> vehicle_classes
                m.name AS model_name,
                m.type AS vehicle_type,
                b.id AS brand_id,
                b.name AS brand_name,
                sc.id AS vehicle_subclass_id,
                sc.name AS subclass_name,
                c.id AS vehicle_class_id,
                c.name AS class_name,
                c.code AS class_code,

                -- Documento probatorio más reciente (Carnet o Título)
                latest_doc.id AS document_id,
                latest_doc.document_type,
                latest_doc.file_path AS document_file_path,
                latest_doc.status AS document_status

            FROM vehicles v
            INNER JOIN users u 
                ON v.user_id = u.id
            INNER JOIN vehicle_models m 
                ON v.model_id = m.id
            INNER JOIN vehicle_brands b 
                ON m.brand_id = b.id
            INNER JOIN vehicle_subclasses sc 
                ON m.vehicle_subclass_id = sc.id
            INNER JOIN vehicle_classes c 
                ON sc.vehicle_class_id = c.id
            LEFT JOIN (
                SELECT d1.id, d1.documentable_id, d1.document_type, d1.file_path, d1.status
                FROM documents d1
                INNER JOIN (
                    SELECT MAX(id) AS max_id
                    FROM documents
                    WHERE documentable_type = 'vehicle'
                      AND document_type IN ('vehicle_registration', 'niv_certificate')
                    GROUP BY documentable_id
                ) d2 ON d1.id = d2.max_id
            ) latest_doc ON latest_doc.documentable_id = v.id
            WHERE v.deleted_at IS NULL;
        ";
        $db->exec($sql);
    }

    public function down(PDO $db): void
    {
        $sql = "DROP VIEW IF EXISTS vw_vehicle_details;";
        $db->exec($sql);
    }
}
