<?php

/**
 * Uso (desde la raíz del proyecto):
 *   php scripts/seed.php               -> ejecuta todos los seeders pendientes
 *   php scripts/seed.php s000001       -> ejecuta un seeder específico
 */

use App\Core\DotEnv;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\SeederRunner;

$rootPath = dirname(__DIR__);

require_once "{$rootPath}/autoload.php";

$envFile = $rootPath . "/.env";
$dotEnv = new DotEnv($envFile);
$dotEnv->load();

$database = new Database();
$seederRunner = new SeederRunner($database);

$specific = $argv[1] ?? null;
$seederRunner->run($specific);

unset($database);
unset($seederRunner);
