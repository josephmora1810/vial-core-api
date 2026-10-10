<?php

namespace App\Routes;

use App\Core\Router;

$router = new Router();

const API_V1 = '/api/v1/';

$router->get(API_V1.'ping', 'HomeController@index');
$router->get(API_V1.'teapot', 'HomeController@teapot');
$router->post(API_V1.'bodytest', 'HomeController@bodyTest');
$router->get(API_V1.'showAnUser/{id}/{userName}', 'HomeController@showAnUser');

return $router;
