<?php

require_once __DIR__ . '/autoload.php';

use App\Core\DotEnv;
use App\Database\Database;
use App\Database\Migrator;


$envFile = __DIR__ .  "/.env";
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