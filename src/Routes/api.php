<?php

namespace App\Routes;

use App\Core\Router;


$router = new Router();

const API_V1 = '/api/v1/';

// ==========================================
// RUTAS PÚBLICAS
// ==========================================

$router->get(API_V1.'ping', 'HomeController@index');
$router->get(API_V1.'teapot', 'HomeController@teapot');
$router->post(API_V1 . 'login', 'AuthController@login');
$router->post(API_V1 . 'register', 'AuthController@register');
$router->post(API_V1 . 'refresh-token', 'AuthController@refreshToken');
$router->post(API_V1 . 'logout', 'AuthController@logout');
$router->post(API_V1 . 'forgot-password', 'AuthController@forgotPassword');
$router->post(API_V1 . 'reset-password', 'AuthController@resetPassword');

// ==========================================
// RUTAS PRIVADAS (Llevan ['auth'])
// ==========================================
$router->get(API_V1 . 'me', 'AuthController@me', ['auth']);

return $router;
