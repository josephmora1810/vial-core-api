<?php

namespace App\Infrastructure\Database\Seeders;

use PDO;
use Throwable;

class s000002_vehicle_classes_and_subclasses_seeder
{
    public function run(PDO $db): void
    {
        $catalog = require __DIR__ . '/data/vehicle_catalog_data.php';

        // 1. Clases de Vehículo oficiales
        $classes = [
            ['code' => 'AUTOMOVIL',           'name' => 'Automovil',           'description' => 'Vehículos de pasajeros de 4 ruedas estándar'],
            ['code' => 'CAMION',              'name' => 'Camion',              'description' => 'Vehículos de transporte de carga pesada'],
            ['code' => 'AUTOBUS',             'name' => 'Autobus',             'description' => 'Vehículos de transporte colectivo de pasajeros'],
            ['code' => 'CAMIONETA',           'name' => 'Camioneta',           'description' => 'Vehículos tipo SUV, Pick-up o utilitarios ligeros'],
            ['code' => 'TAXI',                'name' => 'Taxi',                'description' => 'Vehículos destinados al servicio de transporte público individual'],
            ['code' => 'MOTO',                'name' => 'Moto',                'description' => 'Vehículos de dos o tres ruedas a motor'],
            ['code' => 'RUSTICO',             'name' => 'Rustico',             'description' => 'Vehículos todo terreno 4x4'],
            ['code' => 'MAQUINARIA',          'name' => 'Maquinaria',          'description' => 'Maquinaria pesada, agrícola o de construcción'],
            ['code' => 'TARA',                'name' => 'Tara',                'description' => 'Vehículos con especificación de tara'],
            ['code' => 'TRAILER',             'name' => 'Trailer',             'description' => 'Remolques cerrados o plataformas de arrastre'],
            ['code' => 'MINIBUS',             'name' => 'Minibus',             'description' => 'Vehículos de transporte mediano de pasajeros'],
            ['code' => 'SEMI_REMOLQUE',       'name' => 'Semi Remolque',       'description' => 'Unidades de carga articuladas a un chuto'],
            ['code' => 'REMOLQUE',            'name' => 'Remolque',            'description' => 'Unidades de arrastre de carga independiente'],
            ['code' => 'VEHICULO_ESPECIAL',   'name' => 'Vehiculo Especial',   'description' => 'Vehículos adaptados para funciones especiales'],
            ['code' => 'APARATO_APTO',        'name' => 'Aparato Apto',        'description' => 'Aparatos o equipos mecánicos aptos para circular'],
            ['code' => 'VEHICULO_INCOMPLETO', 'name' => 'Vehículo Incompleto', 'description' => 'Chasis o vehículos sin carrozar'],
            ['code' => 'FUERABORDA',          'name' => 'Fueraborda',          'description' => 'Embarcaciones con motor fuera de borda'],
        ];

        $classStmt = $db->prepare("
            INSERT INTO vehicle_classes (code, name, description)
            VALUES (:code, :name, :description)
            ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)
        ");

        foreach ($classes as $cls) {
            $classStmt->execute($cls);
        }

        // Obtener IDs de las clases
        $stmt = $db->query("SELECT id, name FROM vehicle_classes");
        $classMap = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $classMap[$row['name']] = (int)$row['id'];
        }

        // 2. Subclases / Tipos de Carrocería encadenados a su Clase principal (147 subclases)
        $subclassStmt = $db->prepare("
            INSERT INTO vehicle_subclasses (vehicle_class_id, name)
            VALUES (:class_id, :name)
            ON DUPLICATE KEY UPDATE vehicle_class_id = VALUES(vehicle_class_id)
        ");

        $db->beginTransaction();
        try {
            foreach ($catalog['subclasses'] as [$subclassName, $parentClassName]) {
                $parentClassId = ($parentClassName !== null && isset($classMap[$parentClassName]))
                    ? $classMap[$parentClassName]
                    : null;

                $subclassStmt->execute([
                    'class_id' => $parentClassId,
                    'name'     => $subclassName,
                ]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
