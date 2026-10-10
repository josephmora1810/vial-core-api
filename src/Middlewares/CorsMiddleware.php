<?php

namespace App\Middlewares;

use Closure;
use App\Core\MiddlewareInterface;
use App\Core\Request;

class CorsMiddleware implements MiddlewareInterface{
	public function handle(Request $request, Closure $next){
		//1. Inyectamos las cabeceras CORS para todas las respuestas
		header('Access-Control-Allow-Origin: *'); //En produccion, se cambia * por el dominio del cliente
		header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
		header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

		//2. Si es una Peticion OPTIONS (Preflight), detenemos la ejecucion y respondemos 204
		if($request->method() === 'OPTIONS'){
			header('Access-Control-Max-Age: 86400');
			http_response_code(204);
			exit;
		}

		return $next($request);
	}
}
