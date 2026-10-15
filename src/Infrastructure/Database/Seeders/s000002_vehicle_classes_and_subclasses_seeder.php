<?php

namespace App\Infrastructure\Database\Seeders;

use PDO;

class s000002_vehicle_classes_and_subclasses_seeder
{
    public function run(PDO $db): void
    {
        // 1. Clases de Vehículo oficiales (extraídas de VehiculoHelper.php)
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

        // 2. Subclases / Tipos de Carrocería (extraídos de VehiculoHelper.php)
        $subclasses = [
            'Ambulancia', 'Arrastre', 'Autobus', 'Autobusete', 'Automovil', 'Backhoe Loader',
            'Baranda', 'Baranda/Hierro', 'Barredora', 'Batea', 'Bombeador de Concreto', 'Buque', 'Buque menor de 150 AB', 'Cabina', 'Camion',
            'Camioneta', 'Carga', 'Carga Cisterna', 'Carroceria', 'Casa Rodante', 'Casillero',
            'Cava/Granel', 'Cava', 'Cesta', 'Chasis', 'Chasis/Autobus', 'Chasis/Cabina',
            'Chasis/Minibus', 'Chopper', 'Chuto', 'Cisterna', 'Colectivo', 'Compactadora',
            'Compacto/MPV', 'Convertible', 'Coupe', 'Coupe/Convertible', 'Cross', 'Crossover',
            'Custom', 'Custon', 'Demoledor', 'Deportivo', 'Encava', 'Enduro', 'Especial',
            'Estaca/Baranda', 'Estacas', 'Estacas/Carga', 'Estacas/Estruc/Hierro', 'Excavadora',
            'Ferretero', 'Fibra', 'Funerario', 'Furgon', 'Furgon/Baranda', 'Furgon/Cachucha',
            'Furgoneta', 'Gabinete', 'Gato/Hidraulico', 'Geofisico', 'Granel', 'Grua',
            'Grua/Izamiento', 'Grua/Plataforma', 'Gruas/Pluma', 'Hatch/Back', 'Jaula',
            'Jaul/Ganadera', 'Jaula/Pollera', 'Limousine', 'Low-Boy', 'Lancha a Motor', 'Lancha a Motor de 150 AB', 'Mezcladora', 'Minibus', 'Minicargador',
            'Minivan', 'Montacarga', 'Motocicleta', 'Motoneta', 'Naked', 'Nodriza', 'Pesca', 'Pala Mecanica',
            'Panel', 'Panel/Carga', 'Paseo', 'Pasajero', 'Payloader', 'Pick/Up', 'Pick/Up/D/Cabina',
            'Pick/Up/C/Cabina', 'Pick-Up/Baranda', 'Pick-Up/Carga', 'Pick-Up/Baranda/Hierro',
            'Pick-Up/C Furgon', 'Plat/Estruc/Hierro', 'Platabanda', 'Plataf/Cachucha',
            'Plataf/Baranda/Hierro', 'Plataforma', 'Plataforma/Baranda/Metalica',
            'Plataforma/Desplazable', 'Planta/Electrica', 'Plataforma/Fija', 'Platf/Baranda',
            'Platf/Estaca', 'Platf/B/Hidraulico', 'Platf/G/Hidraulico', 'Porta/Contenedores',
            'Racing', 'Ranchera', 'Recreo', 'Remolque', 'Renegado', 'Retroexcavadora', 'RG-16',
            'Rustico', 'Rustico/Carga', 'sportle', 'Scooter', 'Sedan', 'Silo', 'Sobre Ruedas',
            'Sport/Wagon', 'Sport/Wagon/Convertible', 'Station/Wagon', 'Sport-Wagon/Rustico',
            'Supervan', 'Suv.', 'Tanque', 'Techo/Lona', 'Techo/Duro', 'Touring', 'Tractor',
            'Transportador', 'Trayler', 'Trimovil', 'triciclo', 'Type of ship', 'Utilitario',
            'Vacuum', 'Van', 'Volqueta', 'Volteo'
        ];

        // Mapeo inteligente hacia su clase principal cuando corresponda
        $typeToClass = [
            'Sedan'                  => 'Automovil',
            'Hatch/Back'             => 'Automovil',
            'Coupe'                  => 'Automovil',
            'Coupe/Convertible'      => 'Automovil',
            'Convertible'            => 'Automovil',
            'Deportivo'              => 'Automovil',
            'Paseo'                  => 'Automovil',
            'Station/Wagon'          => 'Automovil',
            'Limousine'              => 'Automovil',

            'Camioneta'              => 'Camioneta',
            'Suv.'                   => 'Camioneta',
            'Crossover'              => 'Camioneta',
            'Pick/Up'                => 'Camioneta',
            'Pick/Up/D/Cabina'        => 'Camioneta',
            'Pick/Up/C/Cabina'        => 'Camioneta',
            'Pick-Up/Baranda'        => 'Camioneta',
            'Pick-Up/Carga'          => 'Camioneta',
            'Pick-Up/Baranda/Hierro' => 'Camioneta',
            'Pick-Up/C Furgon'       => 'Camioneta',
            'Ranchera'               => 'Camioneta',
            'Utilitario'             => 'Camioneta',

            'Rustico'                => 'Rustico',
            'Rustico/Carga'          => 'Rustico',
            'Sport-Wagon/Rustico'    => 'Rustico',
            'Renegado'               => 'Rustico',

            'Motocicleta'            => 'Moto',
            'Motoneta'               => 'Moto',
            'Scooter'                => 'Moto',
            'Chopper'                => 'Moto',
            'Enduro'                 => 'Moto',
            'Naked'                  => 'Moto',
            'Racing'                 => 'Moto',
            'Trimovil'               => 'Moto',
            'triciclo'               => 'Moto',

            'Camion'                 => 'Camion',
            'Chuto'                  => 'Camion',
            'Chasis/Cabina'          => 'Camion',
            'Cava'                   => 'Camion',
            'Cava/Granel'            => 'Camion',
            'Cisterna'               => 'Camion',
            'Volteo'                 => 'Camion',
            'Volqueta'               => 'Camion',
            'Plataforma'             => 'Camion',
            'Furgon'                 => 'Camion',

            'Autobus'                => 'Autobus',
            'Autobusete'             => 'Autobus',
            'Colectivo'              => 'Autobus',
            'Minibus'                => 'Minibus',
            'Minivan'                => 'Camioneta',
            'Van'                    => 'Camioneta',
            'Supervan'               => 'Camioneta',

            'Remolque'               => 'Remolque',
            'Semi Remolque'          => 'Semi Remolque',
            'Batea'                  => 'Semi Remolque',
            'Low-Boy'                => 'Semi Remolque',

            'Grua'                   => 'Vehiculo Especial',
            'Grua/Plataforma'        => 'Vehiculo Especial',
            'Grua/Izamiento'         => 'Vehiculo Especial',
            'Gruas/Pluma'            => 'Vehiculo Especial',
            'Ambulancia'             => 'Vehiculo Especial',
            'Funerario'              => 'Vehiculo Especial',
        ];

        $subclassStmt = $db->prepare("
            INSERT INTO vehicle_subclasses (vehicle_class_id, name) 
            VALUES (:class_id, :name)
            ON DUPLICATE KEY UPDATE vehicle_class_id = VALUES(vehicle_class_id)
        ");

        foreach ($subclasses as $typeName) {
            $parentClassName = $typeToClass[$typeName] ?? null;
            $parentClassId = ($parentClassName && isset($classMap[$parentClassName])) 
                ? $classMap[$parentClassName] 
                : null;

            $subclassStmt->execute([
                'class_id' => $parentClassId,
                'name'     => $typeName,
            ]);
        }
    }
}
