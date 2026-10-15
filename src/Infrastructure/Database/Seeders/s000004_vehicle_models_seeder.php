<?php

namespace App\Infrastructure\Database\Seeders;

use PDO;

class s000004_vehicle_models_seeder
{
    public function run(PDO $db): void
    {
        // 1. Obtener IDs de las clases de vehículos
        $classStmt = $db->query("SELECT id, name FROM vehicle_classes");
        $classes = [];
        while ($row = $classStmt->fetch(PDO::FETCH_ASSOC)) {
            $classes[$row['name']] = (int)$row['id'];
        }

        // 2. Obtener IDs de las marcas
        $brandStmt = $db->query("SELECT id, name FROM vehicle_brands");
        $brands = [];
        while ($row = $brandStmt->fetch(PDO::FETCH_ASSOC)) {
            $brands[strtolower($row['name'])] = (int)$row['id'];
        }

        // 3. Catálogo representativo de modelos más vendidos en Venezuela con su clase
        $modelsByBrand = [
            'toyota' => [
                ['name' => 'Corolla',       'class' => 'Automovil'],
                ['name' => 'Yaris',         'class' => 'Automovil'],
                ['name' => 'Camry',         'class' => 'Automovil'],
                ['name' => 'Starlet',       'class' => 'Automovil'],
                ['name' => 'Hilux',         'class' => 'Camioneta'],
                ['name' => 'Fortuner',      'class' => 'Camioneta'],
                ['name' => '4Runner',       'class' => 'Rustico'],
                ['name' => 'Land Cruiser',  'class' => 'Rustico'],
                ['name' => 'Prado',         'class' => 'Rustico'],
                ['name' => 'Meru',          'class' => 'Rustico'],
                ['name' => 'Machito',       'class' => 'Rustico'],
                ['name' => 'Hiace',         'class' => 'Minibus'],
                ['name' => 'Dyna',          'class' => 'Camion'],
            ],
            'chevrolet' => [
                ['name' => 'Corsa',         'class' => 'Automovil'],
                ['name' => 'Aveo',          'class' => 'Automovil'],
                ['name' => 'Optra',         'class' => 'Automovil'],
                ['name' => 'Spark',         'class' => 'Automovil'],
                ['name' => 'Cruze',         'class' => 'Automovil'],
                ['name' => 'Chevette',      'class' => 'Automovil'],
                ['name' => 'Silverado',     'class' => 'Camioneta'],
                ['name' => 'Tahoe',         'class' => 'Camioneta'],
                ['name' => 'Trailblazer',   'class' => 'Camioneta'],
                ['name' => 'Luv D-Max',     'class' => 'Camioneta'],
                ['name' => 'Grand Vitara',  'class' => 'Rustico'],
                ['name' => 'NPR',           'class' => 'Camion'],
                ['name' => 'NKR',           'class' => 'Camion'],
                ['name' => 'FVR',           'class' => 'Camion'],
            ],
            'ford' => [
                ['name' => 'Fiesta',        'class' => 'Automovil'],
                ['name' => 'Focus',         'class' => 'Automovil'],
                ['name' => 'Fusion',        'class' => 'Automovil'],
                ['name' => 'Laser',         'class' => 'Automovil'],
                ['name' => 'Ka',            'class' => 'Automovil'],
                ['name' => 'Explorer',      'class' => 'Camioneta'],
                ['name' => 'EcoSport',      'class' => 'Camioneta'],
                ['name' => 'Escape',        'class' => 'Camioneta'],
                ['name' => 'Expedition',    'class' => 'Camioneta'],
                ['name' => 'F-150',         'class' => 'Camioneta'],
                ['name' => 'F-250',         'class' => 'Camioneta'],
                ['name' => 'F-350',         'class' => 'Camion'],
                ['name' => 'Cargo',         'class' => 'Camion'],
            ],
            'jeep' => [
                ['name' => 'Grand Cherokee','class' => 'Rustico'],
                ['name' => 'Cherokee',      'class' => 'Rustico'],
                ['name' => 'Wrangler',      'class' => 'Rustico'],
                ['name' => 'CJ-7',          'class' => 'Rustico'],
                ['name' => 'Compass',       'class' => 'Camioneta'],
                ['name' => 'Renegade',      'class' => 'Camioneta'],
            ],
            'renault' => [
                ['name' => 'Twingo',        'class' => 'Automovil'],
                ['name' => 'Clio',          'class' => 'Automovil'],
                ['name' => 'Megane',        'class' => 'Automovil'],
                ['name' => 'Symbol',        'class' => 'Automovil'],
                ['name' => 'Logan',         'class' => 'Automovil'],
                ['name' => 'Sandero',       'class' => 'Automovil'],
                ['name' => 'Duster',        'class' => 'Camioneta'],
                ['name' => 'Koleos',        'class' => 'Camioneta'],
                ['name' => 'Kangoo',        'class' => 'Camioneta'],
            ],
            'fiat' => [
                ['name' => 'Palio',         'class' => 'Automovil'],
                ['name' => 'Siena',         'class' => 'Automovil'],
                ['name' => 'Uno',           'class' => 'Automovil'],
                ['name' => 'Punto',         'class' => 'Automovil'],
                ['name' => 'Idea',          'class' => 'Automovil'],
                ['name' => 'Strada',        'class' => 'Camioneta'],
                ['name' => 'Fiorino',       'class' => 'Camioneta'],
            ],
            'hyundai' => [
                ['name' => 'Getz',          'class' => 'Automovil'],
                ['name' => 'Accent',        'class' => 'Automovil'],
                ['name' => 'Elantra',       'class' => 'Automovil'],
                ['name' => 'Tucson',        'class' => 'Camioneta'],
                ['name' => 'Santa Fe',      'class' => 'Camioneta'],
                ['name' => 'H1',            'class' => 'Minibus'],
                ['name' => 'HD72',          'class' => 'Camion'],
            ],
            'nissan' => [
                ['name' => 'Sentra',        'class' => 'Automovil'],
                ['name' => 'Tiida',         'class' => 'Automovil'],
                ['name' => 'Almera',        'class' => 'Automovil'],
                ['name' => 'March',         'class' => 'Automovil'],
                ['name' => 'X-Trail',       'class' => 'Camioneta'],
                ['name' => 'Pathfinder',    'class' => 'Camioneta'],
                ['name' => 'Murano',        'class' => 'Camioneta'],
                ['name' => 'Frontier',      'class' => 'Camioneta'],
                ['name' => 'Patrol',        'class' => 'Rustico'],
            ],
            'mitsubishi' => [
                ['name' => 'Lancer',        'class' => 'Automovil'],
                ['name' => 'Signo',         'class' => 'Automovil'],
                ['name' => 'Galant',        'class' => 'Automovil'],
                ['name' => 'Montero',       'class' => 'Rustico'],
                ['name' => 'Montero Sport', 'class' => 'Rustico'],
                ['name' => 'Outlander',     'class' => 'Camioneta'],
                ['name' => 'L200',          'class' => 'Camioneta'],
                ['name' => 'Canter',        'class' => 'Camion'],
            ],
            'chery' => [
                ['name' => 'Arauca',        'class' => 'Automovil'],
                ['name' => 'Orinoco',       'class' => 'Automovil'],
                ['name' => 'QQ',            'class' => 'Automovil'],
                ['name' => 'Tiggo',         'class' => 'Camioneta'],
                ['name' => 'Grand Tiger',   'class' => 'Camioneta'],
                ['name' => 'X1',            'class' => 'Camioneta'],
            ],
            'bera' => [
                ['name' => 'BR 150',        'class' => 'Moto'],
                ['name' => 'BR 200',        'class' => 'Moto'],
                ['name' => 'SBR 150',       'class' => 'Moto'],
                ['name' => 'Leon 150',      'class' => 'Moto'],
                ['name' => 'Mustang 150',   'class' => 'Moto'],
                ['name' => 'Kavak 150',     'class' => 'Moto'],
            ],
            'empire keeway' => [
                ['name' => 'Horse 150',     'class' => 'Moto'],
                ['name' => 'Horse II 150',  'class' => 'Moto'],
                ['name' => 'Owen 150',      'class' => 'Moto'],
                ['name' => 'Arsen II 150',  'class' => 'Moto'],
                ['name' => 'Outlook 150',   'class' => 'Moto'],
                ['name' => 'TX 200',        'class' => 'Moto'],
                ['name' => 'RKV 200',       'class' => 'Moto'],
            ],
        ];

        $insertStmt = $db->prepare("
            INSERT INTO vehicle_models (brand_id, vehicle_class_id, name)
            VALUES (:brand_id, :vehicle_class_id, :name)
            ON DUPLICATE KEY UPDATE vehicle_class_id = VALUES(vehicle_class_id)
        ");

        $db->beginTransaction();
        try {
            foreach ($modelsByBrand as $brandKey => $models) {
                if (!isset($brands[$brandKey])) {
                    continue;
                }
                $brandId = $brands[$brandKey];

                foreach ($models as $m) {
                    $classId = isset($classes[$m['class']]) ? $classes[$m['class']] : null;
                    $insertStmt->execute([
                        'brand_id'         => $brandId,
                        'vehicle_class_id' => $classId,
                        'name'             => $m['name'],
                    ]);
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
