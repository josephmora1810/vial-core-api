<?php

declare(strict_types=1);

namespace App\Routes;

use App\Core\Router;

$router = new Router();

$v1 = '/api/v1/';

if (!\defined('API_V1')) {
    \define('API_V1', '/api/v1/');
}

// =========================================================================
// 1. MONITOREO Y SALUD (SYSTEM / PUBLIC)
// =========================================================================
$router->get($v1 . 'ping', 'HomeController@index');
$router->get($v1 . 'teapot', 'HomeController@teapot');
$router->get($v1 . 'home-gallery', 'LandingController@index');

// =========================================================================
// 2. AUTENTICACIÓN Y SEGURIDAD (AUTH)
// =========================================================================
$router->post($v1 . 'register', 'AuthController@register');
$router->post($v1 . 'login', 'AuthController@login');
$router->post($v1 . 'refresh-token', 'AuthController@refreshToken');
$router->post($v1 . 'logout', 'AuthController@logout');
$router->post($v1 . 'scan-identity', 'AuthController@scanIdentity');
$router->post($v1 . 'forgot-password', 'AuthController@forgotPassword');
$router->post($v1 . 'reset-password', 'AuthController@resetPassword');
$router->get($v1 . 'me', 'AuthController@me', ['auth']);

// =========================================================================
// 3. PERFIL DE USUARIO Y DIRECCIONES FAVORITAS (USERS & ADDRESSES)
// =========================================================================
$router->post($v1 . 'users/avatar', 'UserModerationController@uploadAvatar', ['auth']);
$router->post($v1 . 'profile/avatar', 'UserModerationController@uploadAvatar', ['auth']);
$router->get($v1 . 'addresses', 'SavedAddressController@index', ['auth']);
$router->post($v1 . 'addresses', 'SavedAddressController@store', ['auth']);
$router->delete($v1 . 'addresses/{id}', 'SavedAddressController@destroy', ['auth']);

// =========================================================================
// 4. GESTIÓN DE EXPEDIENTES KYC Y DOCUMENTOS (DOCUMENTS)
// =========================================================================
$router->post($v1 . 'documents/upload', 'DocumentController@upload', ['auth']);
$router->get($v1 . 'documents/kyc-status', 'DocumentController@status', ['auth']);
$router->post($v1 . 'vehicles/scan', 'VehicleController@scan', ['auth']);

// =========================================================================
// 5. CATÁLOGO VEHICULAR Y VEHÍCULOS DE USUARIO (VEHICLES)
// =========================================================================
$router->get($v1 . 'vehicles/catalog/classes', 'VehicleController@classes');
$router->get($v1 . 'vehicles/catalog/subclasses', 'VehicleController@subclasses');
$router->get($v1 . 'vehicles/catalog/brands', 'VehicleController@brands');
$router->get($v1 . 'vehicles/catalog/models', 'VehicleController@models');
$router->get($v1 . 'vehicles', 'VehicleController@index', ['auth']);
$router->get($v1 . 'vehicles/{id}', 'VehicleController@show', ['auth']);
$router->post($v1 . 'vehicles', 'VehicleController@store', ['auth', 'permission:register_vehicle']);
$router->delete($v1 . 'vehicles/{id}', 'VehicleController@destroy', ['auth']);

// =========================================================================
// 6. CATÁLOGOS COMERCIALES Y FINANCIEROS (PLANS, BANKS & RATES)
// =========================================================================
$router->get($v1 . 'plans', 'PlanController@index');
$router->get($v1 . 'banks', 'PlanController@banks');
$router->get($v1 . 'exchange-rates', 'PaymentController@exchangeRates');

// =========================================================================
// 7. CONTRATOS DE ASISTENCIA Y SUSCRIPCIONES (CONTRACTS)
// =========================================================================
$router->get($v1 . 'contracts/verify/{code}', 'ContractController@verifyContract');
$router->get($v1 . 'contracts/{id}/pdf', 'ContractController@downloadContractPdf');
$router->post($v1 . 'checkout', 'ContractController@checkout', ['auth', 'permission:buy_service_contract']);
$router->post($v1 . 'contracts/checkout', 'ContractController@checkout', ['auth', 'permission:buy_service_contract']);
$router->get($v1 . 'contracts', 'ContractController@myContracts', ['auth']);
$router->post($v1 . 'contracts/{id}/renew', 'ContractController@renewContract', ['auth']);
$router->post($v1 . 'contracts/{id}/redeem', 'ContractController@redeemContract', ['auth']);
$router->post($v1 . 'contracts/{id}/cancel', 'ContractController@cancelContract', ['auth']);

// =========================================================================
// 8. PASARELA DE PAGOS Y ÓRDENES (PAYMENTS)
// =========================================================================
$router->get($v1 . 'orders', 'PaymentController@myOrders', ['auth']);
$router->post($v1 . 'payments/process', 'PaymentController@process', ['auth']);

// =========================================================================
// 9. ASISTENCIA VIAL SOS, RADAR Y WEBSOCKETS EN TIEMPO REAL (TOWING & PUSHER)
// =========================================================================
$router->post($v1 . 'broadcasting/auth', 'BroadcastingController@auth', ['auth']);
$router->post($v1 . 'sos/quote', 'TowingController@quote', ['auth']);
$router->post($v1 . 'sos', 'TowingController@requestSos', ['auth']);
$router->get($v1 . 'sos/radar', 'TowingController@radar', ['auth', 'role:driver']);
$router->get($v1 . 'radar', 'TowingController@radar', ['auth', 'role:driver']);
$router->get($v1 . 'sos/active', 'TowingController@active', ['auth']);
$router->get($v1 . 'sos/cancellation-reasons', 'TowingController@cancellationReasons', ['auth']);
$router->get($v1 . 'sos/history', 'TowingController@history', ['auth']);
$router->get($v1 . 'sos', 'TowingController@history', ['auth']);
$router->get($v1 . 'sos/{uuid}', 'TowingController@show', ['auth']);
$router->post($v1 . 'sos/{uuid}/accept', 'TowingController@accept', ['auth', 'role:driver']);
$router->put($v1 . 'sos/{uuid}/telemetry', 'TowingController@telemetry', ['auth', 'role:driver']);
$router->post($v1 . 'sos/{uuid}/telemetry', 'TowingController@telemetry', ['auth', 'role:driver']);
$router->post($v1 . 'sos/{uuid}/arrive', 'TowingController@arrive', ['auth', 'role:driver']);
$router->post($v1 . 'sos/{uuid}/complete', 'TowingController@complete', ['auth', 'role:driver']);
$router->post($v1 . 'sos/{uuid}/cancel', 'TowingController@cancel', ['auth']);
$router->post($v1 . 'sos/{uuid}/rate', 'TowingController@rate', ['auth']);

// =========================================================================
// 10. FLOTA DE OPERADORES Y GRUEROS (DRIVER)
// =========================================================================
$router->post($v1 . 'driver/cranes', 'DriverController@registerCrane', ['auth', 'role:driver']);
$router->get($v1 . 'driver/cranes', 'DriverController@listCranes', ['auth', 'role:driver']);
$router->post($v1 . 'driver/cranes/{id}/activate', 'DriverController@activateCrane', ['auth', 'role:driver']);
$router->get($v1 . 'driver/shift/current', 'DriverController@currentShift', ['auth', 'role:driver']);
$router->post($v1 . 'driver/shift/start', 'DriverController@startShift', ['auth', 'role:driver']);
$router->post($v1 . 'driver/shift/end', 'DriverController@endShift', ['auth', 'role:driver']);
$router->post($v1 . 'driver/location', 'DriverController@updateLocation', ['auth', 'role:driver']);
$router->post($v1 . 'driver/license', 'DriverController@uploadLicense', ['auth', 'role:driver']);
$router->get($v1 . 'driver/status', 'DriverController@status', ['auth', 'role:driver']);

// =========================================================================
// 11. RED COMERCIAL DE VENDEDORAS B2B (SELLER)
// =========================================================================
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

// =========================================================================
// 12. ADMINISTRACIÓN, AUDITORÍA Y CONTROL (ADMIN)
// =========================================================================
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
$router->get($v1 . 'admin/commissions', 'AdminCommissionController@listCommissions', ['auth', 'role:admin']);
$router->post($v1 . 'admin/commissions/{id}/pay', 'AdminCommissionController@payCommission', ['auth', 'role:admin']);
$router->put($v1 . 'admin/sellers/{id}/commission-rate', 'AdminCommissionController@updateCommissionRate', ['auth', 'role:admin']);

// =========================================================================
// 13. WEBHOOKS ASÍNCRONOS DE TERCEROS (WEBHOOKS)
// =========================================================================
$router->post($v1 . 'webhooks/bancaribe', 'PaymentController@webhook');
$router->post($v1 . 'webhooks/pusher', 'PusherWebhookController@handle');

return $router;
