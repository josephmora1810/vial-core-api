<?php

/**
 * Uso (desde la raíz del proyecto):
 *   php scripts/migrate.php            -> aplica migraciones pendientes
 *   php scripts/migrate.php rollback   -> revierte las migraciones aplicadas
 */

use App\Core\DotEnv;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Migrator;

// Raíz del proyecto (este script vive en /scripts)
$rootPath = dirname(__DIR__);

require_once "{$rootPath}/autoload.php";


$envFile = $rootPath . "/.env";
$dotEnv = new DotEnv($envFile);
$dotEnv->load();

$database = new Database();
$migrator = new Migrator($database);


$rollback = ($argv[1] ?? null) === 'rollback';

if ($rollback) {
    $migrator->rollback();
} else {
    $migrator->migrate();
}

unset($database);
unset($migrator);