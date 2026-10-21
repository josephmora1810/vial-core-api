<?php

namespace App\Routes;

use App\Core\Router;

$router = new Router();

const API_V1 = '/api/v1/';

// ==========================================
// RUTAS PÚBLICAS
// ==========================================

$router->get(API_V1 . 'ping', 'HomeController@index');
$router->get(API_V1 . 'teapot', 'HomeController@teapot');
$router->post(API_V1 . 'login', 'AuthController@login');
$router->post(API_V1 . 'register', 'AuthController@register');
$router->post(API_V1 . 'scan-identity', 'AuthController@scanIdentity');
$router->post(API_V1 . 'refresh-token', 'AuthController@refreshToken');
$router->post(API_V1 . 'logout', 'AuthController@logout');
$router->post(API_V1 . 'forgot-password', 'AuthController@forgotPassword');
$router->post(API_V1 . 'reset-password', 'AuthController@resetPassword');

// Catálogo de Vehículos (Lectura para selectores de formulario)
$router->get(API_V1 . 'vehicles/catalog/classes', 'VehicleController@classes');
$router->get(API_V1 . 'vehicles/catalog/brands', 'VehicleController@brands');
$router->get(API_V1 . 'vehicles/catalog/models', 'VehicleController@models');

// Catálogo de Planes, Bancos y Tasas BCV (FR-11, FR-16)
$router->get(API_V1 . 'plans', 'PlanController@index');
$router->get(API_V1 . 'banks', 'PlanController@banks');
$router->get(API_V1 . 'exchange-rates', 'PaymentController@exchangeRates');

// Webhook Asíncrono de Bancaribe (FR-29)
$router->post(API_V1 . 'webhooks/bancaribe', 'PaymentController@webhook');

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

// Gestión de Vehículos (FR-18)
$router->get(API_V1 . 'vehicles', 'VehicleController@index', ['auth']);
$router->post(API_V1 . 'vehicles/scan', 'VehicleController@scan', ['auth']);
$router->post(API_V1 . 'vehicles', 'VehicleController@store', ['auth', 'permission:register_vehicle']);
$router->delete(API_V1 . 'vehicles/{id}', 'VehicleController@destroy', ['auth']);

// Suscripción de Contratos, Renovación, Canje, Órdenes, Descarga PDF QR y Pagos Bancaribe (FR-12, FR-14, FR-28, FR-30)
$router->get(API_V1 . 'contracts/{id}/pdf', 'PaymentController@downloadContractPdf');
$router->post(API_V1 . 'checkout', 'PaymentController@checkout', ['auth', 'permission:buy_service_contract']);
$router->post(API_V1 . 'contracts/checkout', 'PaymentController@checkout', ['auth', 'permission:buy_service_contract']);
$router->get(API_V1 . 'contracts', 'PaymentController@myContracts', ['auth']);
$router->post(API_V1 . 'contracts/{id}/renew', 'PaymentController@renewContract', ['auth']);
$router->post(API_V1 . 'contracts/{id}/redeem', 'PaymentController@redeemContract', ['auth']);
$router->post(API_V1 . 'contracts/{id}/cancel', 'PaymentController@cancelContract', ['auth']);
$router->get(API_V1 . 'orders', 'PaymentController@myOrders', ['auth']);
$router->post(API_V1 . 'payments/process', 'PaymentController@process', ['auth']);

// Módulo Comercial de Vendedoras (Búsqueda por Cédula en Redis, Registro Verificado, Contratos y Renovaciones)
$router->get(API_V1 . 'seller/clients/search', 'SellerController@searchClient', ['auth', 'permission:sell_service_contract']);
$router->post(API_V1 . 'seller/clients', 'SellerController@registerClient', ['auth', 'permission:sell_service_contract']);
$router->post(API_V1 . 'seller/vehicles', 'SellerController@registerVehicle', ['auth', 'permission:sell_service_contract']);
$router->post(API_V1 . 'seller/contracts', 'SellerController@createContract', ['auth', 'permission:sell_service_contract']);
$router->get(API_V1 . 'seller/contracts', 'SellerController@myContracts', ['auth', 'permission:sell_service_contract']);
$router->post(API_V1 . 'seller/contracts/{id}/renew', 'SellerController@renewContract', ['auth', 'permission:sell_service_contract']);

// Administración de Planes, Auditoría KYC y Finanzas Bancaribe (Solo Admin / Permisos de Gestión)
$router->get(API_V1 . 'admin/documents', 'DocumentController@adminIndex', ['auth', 'permission:audit_kyc']);
$router->post(API_V1 . 'admin/documents/{id}/review', 'DocumentController@adminReview', ['auth', 'permission:audit_kyc']);
$router->post(API_V1 . 'plans', 'PlanController@store', ['auth', 'permission:manage_catalog']);
$router->put(API_V1 . 'plans/{id}', 'PlanController@update', ['auth', 'permission:manage_catalog']);
$router->delete(API_V1 . 'plans/{id}', 'PlanController@destroy', ['auth', 'permission:manage_catalog']);
$router->post(API_V1 . 'exchange-rates/sync', 'PaymentController@syncExchangeRates', ['auth', 'role:admin']);
$router->get(API_V1 . 'admin/bank/balances', 'PaymentController@adminBankBalances', ['auth', 'role:admin']);
$router->get(API_V1 . 'admin/integrations/logs', 'PaymentController@adminIntegrationLogs', ['auth']);
$router->post(API_V1 . 'admin/contracts/expire-overdue', 'PaymentController@expireOverdueContracts', ['auth', 'role:admin']);

return $router;
