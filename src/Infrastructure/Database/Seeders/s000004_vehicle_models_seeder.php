<?php

namespace App\Infrastructure\Database\Seeders;

use PDO;
use Throwable;

class s000004_vehicle_models_seeder
{
    public function run(PDO $db): void
    {
        $catalog = require __DIR__ . '/data/vehicle_catalog_data.php';

        // 1. Obtener IDs de las subclases / tipos de carrocería
        $subclassStmt = $db->query("SELECT id, name FROM vehicle_subclasses");
        $subclasses = [];
        while ($row = $subclassStmt->fetch(PDO::FETCH_ASSOC)) {
            $subclasses[mb_strtolower(trim($row['name']), 'UTF-8')] = (int)$row['id'];
        }

        // 2. Obtener IDs de las marcas
        $brandStmt = $db->query("SELECT id, name FROM vehicle_brands");
        $brands = [];
        while ($row = $brandStmt->fetch(PDO::FETCH_ASSOC)) {
            $brands[mb_strtolower(trim($row['name']), 'UTF-8')] = (int)$row['id'];
        }

        $insertBrandStmt = $db->prepare("INSERT INTO vehicle_brands (name) VALUES (:name) ON DUPLICATE KEY UPDATE name = VALUES(name)");

        $modelSql = "
            INSERT INTO vehicle_models (brand_id, vehicle_subclass_id, type, name)
            VALUES (:brand_id, :vehicle_subclass_id, :type, :name)
            ON DUPLICATE KEY UPDATE
                vehicle_subclass_id = VALUES(vehicle_subclass_id),
                type = VALUES(type)
        ";
        $insertStmt = $db->prepare($modelSql);

        $db->beginTransaction();
        try {
            foreach ($catalog['models'] as $brandName => $models) {
                $brandKey = mb_strtolower(trim($brandName), 'UTF-8');
                if (!isset($brands[$brandKey])) {
                    $insertBrandStmt->execute(['name' => $brandName]);
                    $brands[$brandKey] = (int)$db->lastInsertId();
                }
                $brandId = $brands[$brandKey];

                foreach ($models as [$modelName, $className, $subclassName, $vehicleType]) {
                    $subclassKey = mb_strtolower(trim((string)$subclassName), 'UTF-8');
                    $subclassId = $subclasses[$subclassKey] ?? 1; // Fallback a la primera subclase si no coincidiera

                    $insertStmt->execute([
                        'brand_id'            => $brandId,
                        'vehicle_subclass_id' => $subclassId,
                        'type'                => $vehicleType ?? 'CAR',
                        'name'                => $modelName,
                    ]);
                }
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
