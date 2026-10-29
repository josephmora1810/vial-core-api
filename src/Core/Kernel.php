<?php

namespace App\Core;

use Exception;

class Kernel
{
    /**
     * 
     * @param Router $router
     * @param array $container
     */
    public function __construct(
        private Router $router,
        private array $container
    ) {}

    public function handle(Request $request): void
    {

        $dispatch = function (Request $req) {
            // Nos traemos las rutas y las formateamos
            $match = $this->router->resolve($req);
            $action = $match['action'];
            $params = $match['params'] ?? [];
            $routePipes = $match['middlewares'] ?? [];

            // Resolvemos los middlewares de la ruta (ejecutando el closure si es lazy, con soporte para parámetros como role:client)
            $resolvedPipes = array_map(function ($pipe) {
                $map = $this->container['route_middlewares'] ?? [];
                if (\is_string($pipe)) {
                    if (isset($map[$pipe])) {
                        return is_callable($map[$pipe]) ? $map[$pipe]() : $map[$pipe];
                    }
                    if (str_contains($pipe, ':')) {
                        [$alias, $argument] = explode(':', $pipe, 2);
                        if (isset($map[$alias])) {
                            $factory = $map[$alias];
                            return is_callable($factory) ? $factory($argument) : $factory;
                        }
                    }
                }
                return $pipe;
            }, $routePipes);

            $runController = function (Request $finalReq) use ($action, $params) {
                [$controllerName, $method] = explode('@', $action);
                $controllerClass = "App\\Controllers\\{$controllerName}";

                if (!class_exists($controllerClass)) {
                    throw new Exception("El controlador {$controllerName} no fue encontrado", 500);
                }

                $controllers = $this->container['controllers'] ?? [];
                $controller = isset($controllers[$controllerClass])
                    ? $controllers[$controllerClass]()
                    : new $controllerClass();

                if (!method_exists($controller, $method)) {
                    throw new Exception("El método {$method} no existe en {$controllerName}", 500);
                }

                // array_values evita conflictos con named arguments en PHP 8+
                $data = [$finalReq, ...array_values($params)];
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
            ->through($this->container['global_middlewares'] ?? [])
            ->then($dispatch);
    }
}