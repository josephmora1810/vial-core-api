<?php

namespace App\Routes;

use App\Core\Router;

$router = new Router();

$v1 = '/api/v1/';

if (!\defined('API_V1')) {
    \define('API_V1', '/api/v1/');
}

// ==========================================
// RUTAS PÚBLICAS
// ==========================================

$router->get($v1 . 'ping', 'HomeController@index');
$router->get($v1 . 'teapot', 'HomeController@teapot');
$router->post($v1 . 'login', 'AuthController@login');
$router->post($v1 . 'register', 'AuthController@register');
$router->post($v1 . 'scan-identity', 'AuthController@scanIdentity');
$router->post($v1 . 'refresh-token', 'AuthController@refreshToken');
$router->post($v1 . 'logout', 'AuthController@logout');
$router->post($v1 . 'forgot-password', 'AuthController@forgotPassword');
$router->post($v1 . 'reset-password', 'AuthController@resetPassword');

// Catálogo de Vehículos (Lectura para selectores de formulario)
$router->get($v1 . 'vehicles/catalog/classes', 'VehicleController@classes');
$router->get($v1 . 'vehicles/catalog/brands', 'VehicleController@brands');
$router->get($v1 . 'vehicles/catalog/models', 'VehicleController@models');

// Catálogo de Planes, Bancos y Tasas BCV (FR-11, FR-16)
$router->get($v1 . 'plans', 'PlanController@index');
$router->get($v1 . 'banks', 'PlanController@banks');
$router->get($v1 . 'exchange-rates', 'PaymentController@exchangeRates');

// Galería Dinámica de la Landing Page (FR-36)
$router->get($v1 . 'home-gallery', 'LandingController@index');

// Webhook Asíncrono de Bancaribe (FR-29)
$router->post($v1 . 'webhooks/bancaribe', 'PaymentController@webhook');

// Webhook de Pusher Channels (Eventos de existencia: channel_occupied, channel_vacated)
$router->post($v1 . 'webhooks/pusher', 'PusherWebhookController@handle');

// ==========================================
// RUTAS PRIVADAS (Llevan ['auth'])
// ==========================================
$router->get($v1 . 'me', 'AuthController@me', ['auth']);

// Handshake de Autenticación de Canales Privados y de Presencia Pusher (OWASP Security)
$router->post($v1 . 'broadcasting/auth', 'BroadcastingController@auth', ['auth']);

// Foto de Perfil / Avatar Polimórfico (FR-36 / SRS pág. 38)
$router->post($v1 . 'users/avatar', 'UserModerationController@uploadAvatar', ['auth']);

$router->post($v1 . 'documents/upload', 'DocumentController@upload', ['auth']);
$router->get($v1 . 'documents/kyc-status', 'DocumentController@status', ['auth']);

// Direcciones Guardadas (FR-19)
$router->get($v1 . 'addresses', 'SavedAddressController@index', ['auth']);
$router->post($v1 . 'addresses', 'SavedAddressController@store', ['auth']);
$router->delete($v1 . 'addresses/{id}', 'SavedAddressController@destroy', ['auth']);

// Gestión de Vehículos (FR-18)
$router->get($v1 . 'vehicles', 'VehicleController@index', ['auth']);
$router->post($v1 . 'vehicles/scan', 'VehicleController@scan', ['auth']);
$router->post($v1 . 'vehicles', 'VehicleController@store', ['auth', 'permission:register_vehicle']);
$router->delete($v1 . 'vehicles/{id}', 'VehicleController@destroy', ['auth']);

// Suscripción de Contratos, Renovación, Canje, Descarga PDF QR y Verificación (FR-12, FR-14, FR-28, FR-30)
$router->get($v1 . 'contracts/verify/{code}', 'ContractController@verifyContract');
$router->get($v1 . 'contracts/{id}/pdf', 'ContractController@downloadContractPdf');
$router->post($v1 . 'checkout', 'ContractController@checkout', ['auth', 'permission:buy_service_contract']);
$router->post($v1 . 'contracts/checkout', 'ContractController@checkout', ['auth', 'permission:buy_service_contract']);
$router->get($v1 . 'contracts', 'ContractController@myContracts', ['auth']);
$router->post($v1 . 'contracts/{id}/renew', 'ContractController@renewContract', ['auth']);
$router->post($v1 . 'contracts/{id}/redeem', 'ContractController@redeemContract', ['auth']);
$router->post($v1 . 'contracts/{id}/cancel', 'ContractController@cancelContract', ['auth']);
$router->get($v1 . 'orders', 'PaymentController@myOrders', ['auth']);
$router->post($v1 . 'payments/process', 'PaymentController@process', ['auth']);

// Módulo Comercial de Vendedoras (Búsqueda por Cédula en Redis, Registro Verificado, Contratos, Renovaciones, Cuentas Bancarias y Comisiones FR-14, FR-15, FR-34)
$router->get($v1 . 'seller/profile', 'SellerController@getProfile', ['auth', 'permission:sell_service_contract']);
$router->post($v1 . 'seller/bank-account', 'SellerController@updateBankAccount', ['auth', 'permission:sell_service_contract']);
$router->put($v1 . 'seller/bank-account', 'SellerController@updateBankAccount', ['auth', 'permission:sell_service_contract']);
$router->get($v1 . 'seller/commissions', 'SellerController@myCommissions', ['auth', 'permission:withdraw_commissions']);
$router->post($v1 . 'seller/commissions/withdraw', 'SellerController@withdrawCommissions', ['auth', 'permission:withdraw_commissions']);
$router->get($v1 . 'seller/clients/search', 'SellerController@searchClient', ['auth', 'permission:sell_service_contract']);
$router->post($v1 . 'seller/clients', 'SellerController@registerClient', ['auth', 'permission:sell_service_contract']);
$router->post($v1 . 'seller/vehicles', 'SellerController@registerVehicle', ['auth', 'permission:sell_service_contract']);
$router->post($v1 . 'seller/contracts', 'SellerController@createContract', ['auth', 'permission:sell_service_contract']);
$router->get($v1 . 'seller/contracts', 'SellerController@myContracts', ['auth', 'permission:sell_service_contract']);
$router->post($v1 . 'seller/contracts/{id}/renew', 'SellerController@renewContract', ['auth', 'permission:sell_service_contract']);

// Módulo de Operadores de Grúas y Validación de Flota (FR-09, FR-10)
$router->post($v1 . 'driver/cranes', 'DriverController@registerCrane', ['auth', 'role:driver']);
$router->get($v1 . 'driver/cranes', 'DriverController@listCranes', ['auth', 'role:driver']);
$router->post($v1 . 'driver/license', 'DriverController@uploadLicense', ['auth', 'role:driver']);
$router->get($v1 . 'driver/status', 'DriverController@status', ['auth', 'role:driver']);

// Módulo de Asistencia Vial (SOS), Telemetría, Matching y Calificaciones (FR-20, FR-22, FR-23, FR-24, FR-25, FR-27)
$router->post($v1 . 'sos', 'TowingController@requestSos', ['auth']);
$router->get($v1 . 'sos/radar', 'TowingController@radar', ['auth', 'role:driver']);
$router->get($v1 . 'radar', 'TowingController@radar', ['auth', 'role:driver']);
$router->get($v1 . 'sos/{uuid}', 'TowingController@show', ['auth']);
$router->post($v1 . 'sos/{uuid}/accept', 'TowingController@accept', ['auth', 'role:driver']);
$router->put($v1 . 'sos/{uuid}/telemetry', 'TowingController@telemetry', ['auth', 'role:driver']);
$router->post($v1 . 'sos/{uuid}/telemetry', 'TowingController@telemetry', ['auth', 'role:driver']);
$router->post($v1 . 'sos/{uuid}/arrive', 'TowingController@arrive', ['auth', 'role:driver']);
$router->post($v1 . 'sos/{uuid}/complete', 'TowingController@complete', ['auth', 'role:driver']);
$router->post($v1 . 'sos/{uuid}/cancel', 'TowingController@cancel', ['auth']);
$router->post($v1 . 'sos/{uuid}/rate', 'TowingController@rate', ['auth']);

// Administración de Planes, Auditoría KYC y Finanzas Bancaribe (Solo Admin / Permisos de Gestión)
$router->post($v1 . 'admin/users/{id}/suspend', 'UserModerationController@suspend', ['auth', 'role:admin']);
$router->post($v1 . 'admin/users/{id}/activate', 'UserModerationController@activate', ['auth', 'role:admin']);
$router->get($v1 . 'admin/home-gallery', 'LandingController@adminIndex', ['auth', 'role:admin']);
$router->post($v1 . 'admin/home-gallery', 'LandingController@store', ['auth', 'role:admin']);
$router->put($v1 . 'admin/home-gallery/{id}', 'LandingController@update', ['auth', 'role:admin']);
$router->delete($v1 . 'admin/home-gallery/{id}', 'LandingController@destroy', ['auth', 'role:admin']);
$router->get($v1 . 'admin/documents', 'DocumentController@adminIndex', ['auth', 'permission:audit_kyc']);
$router->post($v1 . 'admin/documents/{id}/review', 'DocumentController@adminReview', ['auth', 'permission:audit_kyc']);
$router->post($v1 . 'plans', 'PlanController@store', ['auth', 'permission:manage_catalog']);
$router->put($v1 . 'plans/{id}', 'PlanController@update', ['auth', 'permission:manage_catalog']);
$router->delete($v1 . 'plans/{id}', 'PlanController@destroy', ['auth', 'permission:manage_catalog']);
$router->post($v1 . 'exchange-rates/sync', 'PaymentController@syncExchangeRates', ['auth', 'role:admin']);
$router->get($v1 . 'admin/bank/balances', 'PaymentController@adminBankBalances', ['auth', 'role:admin']);
$router->get($v1 . 'admin/integrations/logs', 'PaymentController@adminIntegrationLogs', ['auth', 'role:admin']);
$router->post($v1 . 'admin/contracts/expire-overdue', 'ContractController@expireOverdueContracts', ['auth', 'role:admin']);

// Módulo Administrativo de Comisiones y Billeteras B2B (FR-14, FR-15, FR-34)
$router->get($v1 . 'admin/commissions', 'AdminCommissionController@listCommissions', ['auth', 'role:admin']);
$router->post($v1 . 'admin/commissions/{id}/pay', 'AdminCommissionController@payCommission', ['auth', 'role:admin']);
$router->put($v1 . 'admin/sellers/{id}/commission-rate', 'AdminCommissionController@updateCommissionRate', ['auth', 'role:admin']);

return $router;
