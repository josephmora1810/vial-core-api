<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\{MiddlewareInterface, Request};
use Closure;

class CorsMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Inyectamos las cabeceras CORS dinámicas según .env
        $configuredOrigins = $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*';
        $allowedOrigins = array_filter(array_map('trim', explode(',', $configuredOrigins)));
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array('*', $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: *');
        } elseif (!empty($origin) && in_array($origin, $allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Vary: Origin');
            header('Access-Control-Allow-Credentials: true');
        } else {
            // Fallback para CLI / peticiones sin header Origin
            header('Access-Control-Allow-Origin: *');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, X-Pusher-Key, X-Pusher-Signature');

        // 2. Si es una Peticion OPTIONS (Preflight), detenemos la ejecucion y respondemos 204
        if ($request->method() === 'OPTIONS') {
            header('Access-Control-Max-Age: 86400');
            http_response_code(204);
            exit;
        }

        return $next($request);
    }
}
