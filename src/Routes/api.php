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
$router->post(API_V1 . 'scan-identity', 'AuthController@scanIdentity');
$router->post(API_V1 . 'refresh-token', 'AuthController@refreshToken');
$router->post(API_V1 . 'logout', 'AuthController@logout');
$router->post(API_V1 . 'forgot-password', 'AuthController@forgotPassword');
$router->post(API_V1 . 'reset-password', 'AuthController@resetPassword');

// ==========================================
// RUTAS PRIVADAS (Llevan ['auth'])
// ==========================================
$router->get(API_V1 . 'me', 'AuthController@me', ['auth']);

$router->post(API_V1 . 'documents/upload', 'DocumentController@upload', ['auth']);
$router->get(API_V1 . 'documents/kyc-status', 'DocumentController@status', ['auth']);

// Direcciones Guardadas (FR-19)
$router->get(API_V1 . 'addresses', 'SavedAddressController@index', ['auth']);
$router->post(API_V1 . 'addresses', 'SavedAddressController@store', ['auth']);
$router->delete(API_V1 . 'addresses/{id}', 'SavedAddressController@destroy', ['auth']);

// Catálogo de Vehículos (Lectura para selectores de formulario)
$router->get(API_V1 . 'vehicles/catalog/classes', 'VehicleController@classes');
$router->get(API_V1 . 'vehicles/catalog/brands', 'VehicleController@brands');
$router->get(API_V1 . 'vehicles/catalog/models', 'VehicleController@models');

// Gestión de Vehículos (FR-18)
$router->get(API_V1 . 'vehicles', 'VehicleController@index', ['auth']);
$router->post(API_V1 . 'vehicles/scan', 'VehicleController@scan', ['auth']);
$router->post(API_V1 . 'vehicles', 'VehicleController@store', ['auth', 'permission:register_vehicle']);
$router->delete(API_V1 . 'vehicles/{id}', 'VehicleController@destroy', ['auth']);

return $router;
