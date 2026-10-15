<?php

namespace App\Middlewares;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use Closure;
use Exception;

/**
 * Middleware que garantiza que todas las respuestas de la API sean application/json
 * y valida que las peticiones con cuerpo especifiquen Content-Type: application/json
 * (permitiendo multipart/form-data para la subida de documentos).
 */
class EnforceJsonResponseMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Inyectar siempre el encabezado de respuesta JSON
        header('Content-Type: application/json; charset=utf-8');

        // 2. Si el cliente especifica Accept, verificar que acepte application/json
        $accept = $request->header('Accept');
        if (!empty($accept) && !$request->wantsJson()) {
            throw new Exception("Encabezado Accept no compatible. Esta API solo genera respuestas en formato application/json.", 406);
        }

        // 3. Para métodos con cuerpo (POST, PUT, PATCH), validar Content-Type
        $method = $request->method();
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $contentType = $request->header('Content-Type', '');
            $contentLength = (int)$request->header('Content-Length', '0');
            $hasFiles = !empty($_FILES);

            // Si hay archivos subidos o es multipart/form-data, permitimos el paso
            $isMultipart = str_contains($contentType, 'multipart/form-data');

            if (!$hasFiles && !$isMultipart && $contentLength > 0) {
                if (!$request->isJson()) {
                    throw new Exception("El encabezado Content-Type debe ser 'application/json' o 'multipart/form-data'.", 415);
                }

                // Verificar si el JSON enviado está mal formado
                $rawInput = file_get_contents('php://input');
                if (!empty($rawInput)) {
                    json_decode($rawInput);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception("El cuerpo de la solicitud contiene un JSON inválido o mal formado.", 400);
                    }
                }
            }
        }

        return $next($request);
    }
}
