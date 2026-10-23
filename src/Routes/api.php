<?php

namespace App\Routes;

use App\Core\Router;

$router = new Router();

if (!defined(__NAMESPACE__ . '\\API_V1')) {
    define(__NAMESPACE__ . '\\API_V1', '/api/v1/');
}

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

// Galería Dinámica de la Landing Page (FR-36)
$router->get(API_V1 . 'home-gallery', 'LandingController@index');

// Webhook Asíncrono de Bancaribe (FR-29)
$router->post(API_V1 . 'webhooks/bancaribe', 'PaymentController@webhook');

// Webhook de Pusher Channels (Eventos de existencia: channel_occupied, channel_vacated)
$router->post(API_V1 . 'webhooks/pusher', 'PusherWebhookController@handle');

// ==========================================
// RUTAS PRIVADAS (Llevan ['auth'])
// ==========================================
$router->get(API_V1 . 'me', 'AuthController@me', ['auth']);

// Foto de Perfil / Avatar Polimórfico (FR-36 / SRS pág. 38)
$router->post(API_V1 . 'users/avatar', 'UserModerationController@uploadAvatar', ['auth']);

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

// Suscripción de Contratos, Renovación, Canje, Descarga PDF QR y Verificación (FR-12, FR-14, FR-28, FR-30)
$router->get(API_V1 . 'contracts/verify/{code}', 'ContractController@verifyContract');
$router->get(API_V1 . 'contracts/{id}/pdf', 'ContractController@downloadContractPdf');
$router->post(API_V1 . 'checkout', 'ContractController@checkout', ['auth', 'permission:buy_service_contract']);
$router->post(API_V1 . 'contracts/checkout', 'ContractController@checkout', ['auth', 'permission:buy_service_contract']);
$router->get(API_V1 . 'contracts', 'ContractController@myContracts', ['auth']);
$router->post(API_V1 . 'contracts/{id}/renew', 'ContractController@renewContract', ['auth']);
$router->post(API_V1 . 'contracts/{id}/redeem', 'ContractController@redeemContract', ['auth']);
$router->post(API_V1 . 'contracts/{id}/cancel', 'ContractController@cancelContract', ['auth']);
$router->get(API_V1 . 'orders', 'PaymentController@myOrders', ['auth']);
$router->post(API_V1 . 'payments/process', 'PaymentController@process', ['auth']);

// Módulo Comercial de Vendedoras (Búsqueda por Cédula en Redis, Registro Verificado, Contratos, Renovaciones, Cuentas Bancarias y Comisiones FR-14, FR-15, FR-34)
$router->get(API_V1 . 'seller/profile', 'SellerController@getProfile', ['auth', 'permission:sell_service_contract']);
$router->post(API_V1 . 'seller/bank-account', 'SellerController@updateBankAccount', ['auth', 'permission:sell_service_contract']);
$router->put(API_V1 . 'seller/bank-account', 'SellerController@updateBankAccount', ['auth', 'permission:sell_service_contract']);
$router->get(API_V1 . 'seller/commissions', 'SellerController@myCommissions', ['auth', 'permission:withdraw_commissions']);
$router->post(API_V1 . 'seller/commissions/withdraw', 'SellerController@withdrawCommissions', ['auth', 'permission:withdraw_commissions']);
$router->get(API_V1 . 'seller/clients/search', 'SellerController@searchClient', ['auth', 'permission:sell_service_contract']);
$router->post(API_V1 . 'seller/clients', 'SellerController@registerClient', ['auth', 'permission:sell_service_contract']);
$router->post(API_V1 . 'seller/vehicles', 'SellerController@registerVehicle', ['auth', 'permission:sell_service_contract']);
$router->post(API_V1 . 'seller/contracts', 'SellerController@createContract', ['auth', 'permission:sell_service_contract']);
$router->get(API_V1 . 'seller/contracts', 'SellerController@myContracts', ['auth', 'permission:sell_service_contract']);
$router->post(API_V1 . 'seller/contracts/{id}/renew', 'SellerController@renewContract', ['auth', 'permission:sell_service_contract']);

// Módulo de Operadores de Grúas y Validación de Flota (FR-09, FR-10)
$router->post(API_V1 . 'driver/cranes', 'DriverController@registerCrane', ['auth', 'role:driver']);
$router->get(API_V1 . 'driver/cranes', 'DriverController@listCranes', ['auth', 'role:driver']);
$router->post(API_V1 . 'driver/license', 'DriverController@uploadLicense', ['auth', 'role:driver']);
$router->get(API_V1 . 'driver/status', 'DriverController@status', ['auth', 'role:driver']);

// Módulo de Asistencia Vial (SOS), Telemetría, Matching y Calificaciones (FR-20, FR-22, FR-23, FR-24, FR-25, FR-27)
$router->post(API_V1 . 'sos', 'TowingController@requestSos', ['auth']);
$router->get(API_V1 . 'sos/radar', 'TowingController@radar', ['auth', 'role:driver']);
$router->get(API_V1 . 'radar', 'TowingController@radar', ['auth', 'role:driver']);
$router->get(API_V1 . 'sos/{uuid}', 'TowingController@show', ['auth']);
$router->post(API_V1 . 'sos/{uuid}/accept', 'TowingController@accept', ['auth', 'role:driver']);
$router->put(API_V1 . 'sos/{uuid}/telemetry', 'TowingController@telemetry', ['auth', 'role:driver']);
$router->post(API_V1 . 'sos/{uuid}/telemetry', 'TowingController@telemetry', ['auth', 'role:driver']);
$router->post(API_V1 . 'sos/{uuid}/arrive', 'TowingController@arrive', ['auth', 'role:driver']);
$router->post(API_V1 . 'sos/{uuid}/complete', 'TowingController@complete', ['auth', 'role:driver']);
$router->post(API_V1 . 'sos/{uuid}/cancel', 'TowingController@cancel', ['auth']);
$router->post(API_V1 . 'sos/{uuid}/rate', 'TowingController@rate', ['auth']);

// Administración de Planes, Auditoría KYC y Finanzas Bancaribe (Solo Admin / Permisos de Gestión)
$router->post(API_V1 . 'admin/users/{id}/suspend', 'UserModerationController@suspend', ['auth', 'role:admin']);
$router->post(API_V1 . 'admin/users/{id}/activate', 'UserModerationController@activate', ['auth', 'role:admin']);
$router->get(API_V1 . 'admin/home-gallery', 'LandingController@adminIndex', ['auth', 'role:admin']);
$router->post(API_V1 . 'admin/home-gallery', 'LandingController@store', ['auth', 'role:admin']);
$router->put(API_V1 . 'admin/home-gallery/{id}', 'LandingController@update', ['auth', 'role:admin']);
$router->delete(API_V1 . 'admin/home-gallery/{id}', 'LandingController@destroy', ['auth', 'role:admin']);
$router->get(API_V1 . 'admin/documents', 'DocumentController@adminIndex', ['auth', 'permission:audit_kyc']);
$router->post(API_V1 . 'admin/documents/{id}/review', 'DocumentController@adminReview', ['auth', 'permission:audit_kyc']);
$router->post(API_V1 . 'plans', 'PlanController@store', ['auth', 'permission:manage_catalog']);
$router->put(API_V1 . 'plans/{id}', 'PlanController@update', ['auth', 'permission:manage_catalog']);
$router->delete(API_V1 . 'plans/{id}', 'PlanController@destroy', ['auth', 'permission:manage_catalog']);
$router->post(API_V1 . 'exchange-rates/sync', 'PaymentController@syncExchangeRates', ['auth', 'role:admin']);
$router->get(API_V1 . 'admin/bank/balances', 'PaymentController@adminBankBalances', ['auth', 'role:admin']);
$router->get(API_V1 . 'admin/integrations/logs', 'PaymentController@adminIntegrationLogs', ['auth', 'role:admin']);
$router->post(API_V1 . 'admin/contracts/expire-overdue', 'ContractController@expireOverdueContracts', ['auth', 'role:admin']);

// Módulo Administrativo de Comisiones y Billeteras B2B (FR-14, FR-15, FR-34)
$router->get(API_V1 . 'admin/commissions', 'AdminCommissionController@listCommissions', ['auth', 'role:admin']);
$router->post(API_V1 . 'admin/commissions/{id}/pay', 'AdminCommissionController@payCommission', ['auth', 'role:admin']);
$router->put(API_V1 . 'admin/sellers/{id}/commission-rate', 'AdminCommissionController@updateCommissionRate', ['auth', 'role:admin']);

return $router;
