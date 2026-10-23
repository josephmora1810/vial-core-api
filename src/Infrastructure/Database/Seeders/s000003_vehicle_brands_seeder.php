<?php

namespace App\Infrastructure\Database\Seeders;

use PDO;
use Throwable;

class s000003_vehicle_brands_seeder
{
    public function run(PDO $db): void
    {
        $catalog = require __DIR__ . '/data/vehicle_catalog_data.php';
        $marcas = $catalog['brands'];

        $uniqueMarcas = array_values(array_unique(array_filter(array_map('trim', $marcas))));

        $stmt = $db->prepare("INSERT INTO vehicle_brands (name) VALUES (:name) ON DUPLICATE KEY UPDATE name = VALUES(name)");

        $db->beginTransaction();
        try {
            foreach ($uniqueMarcas as $brandName) {
                if ($brandName !== '') {
                    $stmt->execute(['name' => $brandName]);
                }
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
