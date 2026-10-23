<?php

declare(strict_types=1);

// bootstrap/container.php

use App\Infrastructure\Providers\{
    ControllerServiceProvider,
    InfrastructureServiceProvider,
    MiddlewareServiceProvider,
    RepositoryServiceProvider
};

$infra = InfrastructureServiceProvider::register();
$repos = RepositoryServiceProvider::register($infra);
$controllers = ControllerServiceProvider::register($repos, $infra);
$middlewares = MiddlewareServiceProvider::register($repos, $infra);

return [
    'controllers'        => $controllers,
    'services'           => InfrastructureServiceProvider::createServices($repos, $infra),
    'route_middlewares'  => $middlewares['route_middlewares'],
    'global_middlewares' => $middlewares['global_middlewares'],
];
