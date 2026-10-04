<?php

namespace App\Routes;

use App\Core\Router;

$router = new Router();

const API_V1 = '/api/v1/';

$router->get(API_V1.'ping', 'HomeController@index');
$router->get(API_V1.'teapot', 'HomeController@teapot');

return $router;
