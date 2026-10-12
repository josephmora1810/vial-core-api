<?php

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// Si el archivo físico existe dentro de public/, PHP lo entrega directamente:
if ($uri !== '/' && file_exists(__DIR__ . '/public' . $uri)) {
    return false;
}

// Si la URL no es un archivo físico, se la pasamos a index.php para que el Router la procese:
require_once __DIR__ . '/public/index.php';