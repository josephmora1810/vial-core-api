<?php

require_once __DIR__ . '/../autoload.php';

use App\Core\DotEnv;
use App\Core\Request;
use App\Core\Response;
use App\Core\Kernel;

// 1. Variables de entorno
new DotEnv(__DIR__ . '/../.env')->load();

// 2. Cargar rutas y contenedor de dependencias
$router = require_once __DIR__ . '/../src/Routes/api.php';
$container = require_once __DIR__ . '/../bootstrap/container.php';

// 3. Ejecutar la aplicación
try {
    $kernel = new Kernel($router, $container);
    $kernel->handle(new Request());
} catch (Throwable $e) {
    $code = $e->getCode();
    $httpCode = (is_int($code) && $code >= 100 && $code < 600) ? $code : 500;

    // Si estás en producción, oculta errores 500 internos (ej. fallos de SQL)
    $isDev = ($_ENV['APP_ENV'] ?? 'local') === 'local';
    $message = ($httpCode === 500 && !$isDev) 
        ? 'Error interno del servidor' 
        : $e->getMessage();

    Response::json([], $httpCode, 'error', $message);
}