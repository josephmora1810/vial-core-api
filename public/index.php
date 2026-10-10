<?php

require_once __DIR__ . '/../autoload.php';

use App\Core\Request;
use App\Core\Response;
use App\Core\DotEnv;
use App\Database\Database;
use App\Middlewares\CorsMiddleware;
use App\Core\Pipeline;

//cargamos las variables de entorno
$envFile = __DIR__ .  "/../.env";
$dotEnv = new DotEnv($envFile);
$dotEnv->load();

//cargamos el archivo de rutas
$router = require_once __DIR__ . '/../src/Routes/api.php';
$request = new Request();

//cargamos la conexión a la base de datos
$databaseClass = new Database();
$dbConnection = $databaseClass->getConnection();

$dispatch = function ($request) use ($router){
	
	//resolvemos la ruta actual
	$match = $router->resolve($request);
	$action = $match['action']; // Obtenemos 'IndexController@index'
	$params =  $match['params']; // Obtenemos ['id' => '2']

	/**
	 * Separamos el string por el arroba
	 *  $controllerName = 'IndexController'
	 *  $method = 'índex'
	 */

	list($controllerName, $method) = explode('@', $action);

	// Los controladores se guardan en App\Controllers
	$controllerClass = "App\\Controllers\\" . $controllerName;

	// Revisamos si el controlador y su metodo existen de verdad, si no, se lanza una excepción
	if(class_exists($controllerClass)){
		$controller = new $controllerClass();
		$data = array_merge([$request], $params); // Agregamos el objeto Request al array de parámetros
		if(method_exists($controller, $method)){ // Agregamos el objeto Request al inicio del array de parámetros
			call_user_func_array([$controller, $method], $data);
		}else{
			throw new Exception("El método {$method} no existe en {$controllerName}", 500);
		}
	}else{
		throw new Exception("El controlador {$controllerName} no fué encontrado", 500);
	}
};

//ejecutamos el Dispatcher
try {
	(new Pipeline())
		->send($request)
		->through([
			CorsMiddleware::class,
		])
		->then($dispatch);
}catch (Throwable $e){
	$code = $e->getCode() ?: 500;
	$code = ($code >= 100 && $code < 600) ? $code : 500;
	Response::json([], $code, 'error', $e->getMessage());
}
