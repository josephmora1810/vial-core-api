<?php 

namespace App\Core;

use App\Core\Request;
use Exception;

class Router{
	//definimos el tipo de peticiones que puede hacer el sistema
	protected array $routes = [
		'GET' => [],
		'POST' => [],
		'PUT' => [],
		'DELETE' => [],
		'PATCH' => []
	];

	//
	public function resolve(Request $request) : array {
		$method = $request->method();
		$uri = $request->uri();

		if(!isset($this->routes[$method])) throw new Exception('Route not Found', 404);

		foreach($this->routes[$method] as $route => $routeData){
			$pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route);
			$pattern = "#^$pattern$#";

			if(preg_match($pattern, $uri, $matches)){
				$params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
				return [
					'action' => $routeData['action'],
					'middlewares' => $routeData['middlewares'],
					'params' => $params,
				];
			}
		}

		throw new Exception('Route not Found', 404);
	}
	
	protected function cleanUri(string $uri) : string {
		return '/' . trim($uri, '/');
	}

	public function get(string $uri, string $action, array $middlewares = []){
		$this->routes['GET'][$this->cleanUri($uri)] = [
			'action' => $action, 
			'middlewares' => $middlewares
		];
	}

	public function post(string $uri, string $action, array $middlewares = []){
		$this->routes['POST'][$this->cleanUri($uri)] = [
			'action' => $action, 
			'middlewares' => $middlewares
		];
	}

	public function put(string $uri, string $action, array $middlewares = []){
		$this->routes['PUT'][$this->cleanUri($uri)] = [
			'action' => $action, 
			'middlewares' => $middlewares
		];
	}

	public function delete(string $uri, string $action, array $middlewares = []){
		$this->routes['DELETE'][$this->cleanUri($uri)] = [
			'action' => $action, 
			'middlewares' => $middlewares
		];
	}

	public function patch(string $uri, string $action, array $middlewares = []){
		$this->routes['PATCH'][$this->cleanUri($uri)] = [
			'action' => $action, 
			'middlewares' => $middlewares
		];
	}
}
