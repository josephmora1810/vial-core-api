<?php

require_once __DIR__ . '/../autoload.php';

use App\Application\UseCases\RegisterUser;
use App\Core\Request;
use App\Core\Response;
use App\Core\DotEnv;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Redis;
use App\Middlewares\CorsMiddleware;
use App\Core\Pipeline;
use App\Middlewares\ThrottleMiddleware;
use App\Core\JwtService;
use App\Middlewares\AuthMiddleware;
use App\Infrastructure\Database\Repositories\MariaDbUserRepository;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Application\UseCases\LoginUser;

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

//cargamos la conexión a redis
$redisClass = new Redis();
$redisConnection = $redisClass->getConnection();

//cargamos el servicio jwt
$jwtService = new JwtService();

//Inyeccion de dependencias
$userRepository = new MariaDbUserRepository($dbConnection);

//fabrica de controladores
$container = [
    AuthController::class => fn() => new AuthController(
        new LoginUser($userRepository, $jwtService),
        new RegisterUser($userRepository, $jwtService)
    ),
    HomeController::class => fn() => new HomeController(),
];

//mapeo de middlewares y dependencias
$routeMiddlewaresMap = [
    'auth' => new AuthMiddleware(
        new JwtService(),
        new MariaDbUserRepository($dbConnection)
    )
];

$dispatch = function ($request) use ($router, $routeMiddlewaresMap, $container) {
	
	//resolvemos la ruta actual
	$match = $router->resolve($request);
	$action = $match['action']; // Obtenemos 'IndexController@index'
	$params =  $match['params']; // Obtenemos ['id' => '2']
    $routePipes = $match['middlewares'] ?? [];

	// Convertimos los nombres/alias de la ruta a los objetos de middleware reales
    $resolvedPipes = array_map(function ($pipe) use ($routeMiddlewaresMap) {
        if (is_string($pipe) && isset($routeMiddlewaresMap[$pipe])) {
            return $routeMiddlewaresMap[$pipe];
        }
        return $pipe;
    }, $routePipes);

	// Acción final que ejecuta el controlador
    $runController = function ($req) use ($action, $params, $container) {
        list($controllerName, $method) = explode('@', $action);
        $controllerClass = "App\\Controllers\\" . $controllerName;
        if (!class_exists($controllerClass)) {
            throw new Exception("El controlador {$controllerName} no fue encontrado", 500);
        }
        // Si el controlador está registrado en la fábrica, lo creamos con sus dependencias;
        // si no, fallback al new clásico:
        $controller = isset($container[$controllerClass]) 
            ? $container[$controllerClass]() 
            : new $controllerClass();
        if (!method_exists($controller, $method)) {
            throw new Exception("El método {$method} no existe en {$controllerName}", 500);
        }
        $data = array_merge([$req], $params);
        call_user_func_array([$controller, $method], $data);
    };

	// Si la ruta tiene middlewares específicos (ej. 'auth'), la pasamos por su propio pipeline
    if (!empty($resolvedPipes)) {
        (new Pipeline())
            ->send($request)
            ->through($resolvedPipes)
            ->then($runController);
    } else {
        // Si es pública, ejecutamos el controlador directo
        $runController($request);
    }
};

// Ejecutamos el Pipeline GLOBAL (CORS y Throttle aplican a TODO)
try {
    (new Pipeline())
        ->send($request)
        ->through([
            new CorsMiddleware(),
            new ThrottleMiddleware($redisClass)
        ])
        ->then($dispatch);
} catch (Throwable $e) {
    $code = $e->getCode() ?: 500;
    $code = ($code >= 100 && $code < 600) ? $code : 500;
    Response::json([], $code, 'error', $e->getMessage());
}
