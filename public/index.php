<?php

require_once __DIR__ . '/../autoload.php';

use App\Core\Request;
use App\Core\Response;
use App\Core\DotEnv;
use App\Database\Database;

use Exception;

//cargamos las variables de entorno
$envFile = __DIR__ .  "/../.env";
$dotEnv = new DotEnv($envFile);
$dotEnv->load();

//cargamos el archivo de rutas
$router = require_once __DIR__ . '/../src/Routes/api.php';
$request = new Request();

$databaseClass = new Database();
$dbConnection = $databaseClass->getConnection();

//ejecutamos el Dispatcher
try {
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

		if(method_exists($controller, $method)){
			call_user_func_array([$controller, $method], $params);
		}else{
			throw new Exception("El método {$method} no existe en {$controllerName}", 500);
		}
	}else{
		throw new Exception("El controlador {$controllerName} no fué encontrado", 500);
	}
}catch (Exception $e){
	$code = $e->getCode() ?: 500;
	Response::json([], $code, 'error', $e->getMessage());
}
