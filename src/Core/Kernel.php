<?php

namespace App\Core;

use Exception;

class Kernel
{
    public function __construct(
        private Router $router,
        private array $config
    ) {}

    public function handle(Request $request): void
    {
        $dispatch = function (Request $req) {
            $match = $this->router->resolve($req);
            $action = $match['action'];
            $params = $match['params'] ?? [];
            $routePipes = $match['middlewares'] ?? [];

            // Resolvemos los middlewares de la ruta (ejecutando el closure si es lazy)
            $resolvedPipes = array_map(function ($pipe) {
                $map = $this->config['route_middlewares'] ?? [];
                if (\is_string($pipe) && isset($map[$pipe])) {
                    return is_callable($map[$pipe]) ? $map[$pipe]() : $map[$pipe];
                }
                return $pipe;
            }, $routePipes);

            $runController = function (Request $finalReq) use ($action, $params) {
                [$controllerName, $method] = explode('@', $action);
                $controllerClass = "App\\Controllers\\{$controllerName}";

                if (!class_exists($controllerClass)) {
                    throw new Exception("El controlador {$controllerName} no fue encontrado", 500);
                }

                $controllers = $this->config['controllers'] ?? [];
                $controller = isset($controllers[$controllerClass])
                    ? $controllers[$controllerClass]()
                    : new $controllerClass();

                if (!method_exists($controller, $method)) {
                    throw new Exception("El método {$method} no existe en {$controllerName}", 500);
                }

                // array_values evita conflictos con named arguments en PHP 8+
                $data = array_merge([$finalReq], array_values($params));
                \call_user_func_array([$controller, $method], $data);
            };

            if (!empty($resolvedPipes)) {
                new Pipeline()
                    ->send($req)
                    ->through($resolvedPipes)
                    ->then($runController);
            } else {
                $runController($req);
            }
        };

        // Pipeline Global
        new Pipeline()
            ->send($request)
            ->through($this->config['global_middlewares'] ?? [])
            ->then($dispatch);
    }
}