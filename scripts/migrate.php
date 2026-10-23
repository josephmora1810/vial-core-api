<?php

/**
 * Gestor de Migraciones de Base de Datos (UbikGo Core API)
 *
 * Uso (desde la raíz del proyecto):
 *   php scripts/migrate.php               -> aplica migraciones pendientes asignando nuevo step
 *   php scripts/migrate.php rollback      -> revierte únicamente las migraciones del último step
 *   php scripts/migrate.php rollback 2    -> revierte las migraciones de los últimos 2 steps
 *   php scripts/migrate.php rollback --all -> revierte todas las migraciones registradas
 *   php scripts/migrate.php status        -> visualiza el historial de migraciones y sus steps
 */

use App\Core\DotEnv;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Migrator;

$rootPath = dirname(__DIR__);

require_once "{$rootPath}/autoload.php";

$envFile = $rootPath . "/.env";
$dotEnv = new DotEnv($envFile);
$dotEnv->load();

$database = new Database();
$migrator = new Migrator($database);

$command = $argv[1] ?? null;

if ($command === 'rollback') {
    $steps = 1;

    if (isset($argv[2])) {
        if ($argv[2] === '--all') {
            $steps = null;
        } elseif (is_numeric($argv[2])) {
            $steps = (int)$argv[2];
        } elseif (preg_match('/^--steps?=(\d+)$/', $argv[2], $matches)) {
            $steps = (int)$matches[1];
        }
    }

    $migrator->rollback($steps);
} elseif ($command === 'status') {
    $history = $migrator->getMigrationHistory();
    if (empty($history)) {
        echo "No hay migraciones aplicadas en la base de datos.\n";
    } else {
        echo str_pad("ID", 6) . str_pad("STEP", 8) . str_pad("MIGRACIÓN", 65) . "FECHA\n";
        echo str_repeat("-", 100) . "\n";
        foreach ($history as $row) {
            echo str_pad((string)$row['id'], 6)
                . str_pad((string)$row['step'], 8)
                . str_pad((string)$row['migration'], 65)
                . $row['created_at'] . "\n";
        }
    }
} else {
    $migrator->migrate();
}

unset($database);
unset($migrator);
