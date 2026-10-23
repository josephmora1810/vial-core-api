<?php

namespace Tests\Unit\Controllers;

use App\Controllers\ContractController;
use App\Controllers\PaymentController;
use App\Controllers\TowingController;
use App\Core\Request;
use PHPUnit\Framework\TestCase;

class ContainerControllerResolutionTest extends TestCase
{
    public function testControllersAreRegisteredAndResolvableInContainer(): void
    {
        $container = require dirname(__DIR__, 3) . '/bootstrap/container.php';

        $this->assertArrayHasKey('controllers', $container);
        $this->assertArrayHasKey(ContractController::class, $container['controllers']);
        $this->assertArrayHasKey(PaymentController::class, $container['controllers']);
        $this->assertArrayHasKey(TowingController::class, $container['controllers']);

        $contractController = $container['controllers'][ContractController::class]();
        $this->assertInstanceOf(ContractController::class, $contractController);

        $paymentController = $container['controllers'][PaymentController::class]();
        $this->assertInstanceOf(PaymentController::class, $paymentController);

        $towingController = $container['controllers'][TowingController::class]();
        $this->assertInstanceOf(TowingController::class, $towingController);
    }

    public function testRouterResolvesContractRoutesToContractController(): void
    {
        $router = require dirname(__DIR__, 3) . '/src/Routes/api.php';

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/v1/checkout';
        $resolvedCheckout = $router->resolve(new Request());
        $this->assertSame('ContractController@checkout', $resolvedCheckout['action']);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/v1/contracts';
        $resolvedContracts = $router->resolve(new Request());
        $this->assertSame('ContractController@myContracts', $resolvedContracts['action']);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/v1/contracts/verify/ABC-123';
        $resolvedVerify = $router->resolve(new Request());
        $this->assertSame('ContractController@verifyContract', $resolvedVerify['action']);
        $this->assertSame('ABC-123', $resolvedVerify['params']['code']);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/v1/contracts/5/cancel';
        $resolvedCancel = $router->resolve(new Request());
        $this->assertSame('ContractController@cancelContract', $resolvedCancel['action']);
        $this->assertSame('5', $resolvedCancel['params']['id']);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/v1/contracts/5/renew';
        $resolvedRenew = $router->resolve(new Request());
        $this->assertSame('ContractController@renewContract', $resolvedRenew['action']);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/v1/contracts/5/redeem';
        $resolvedRedeem = $router->resolve(new Request());
        $this->assertSame('ContractController@redeemContract', $resolvedRedeem['action']);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/v1/admin/contracts/expire-overdue';
        $resolvedExpire = $router->resolve(new Request());
        $this->assertSame('ContractController@expireOverdueContracts', $resolvedExpire['action']);
    }

    public function testRouterResolvesPaymentRoutesToPaymentController(): void
    {
        $router = require dirname(__DIR__, 3) . '/src/Routes/api.php';

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/v1/orders';
        $resolvedOrders = $router->resolve(new Request());
        $this->assertSame('PaymentController@myOrders', $resolvedOrders['action']);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/v1/payments/process';
        $resolvedPay = $router->resolve(new Request());
        $this->assertSame('PaymentController@process', $resolvedPay['action']);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/v1/exchange-rates';
        $resolvedRates = $router->resolve(new Request());
        $this->assertSame('PaymentController@exchangeRates', $resolvedRates['action']);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/v1/webhooks/bancaribe';
        $resolvedWebhook = $router->resolve(new Request());
        $this->assertSame('PaymentController@webhook', $resolvedWebhook['action']);
    }

    public function testRouterResolvesTowingRoutesToTowingController(): void
    {
        $router = require dirname(__DIR__, 3) . '/src/Routes/api.php';

        $testUuid = '0192e21b-85fa-7090-bb97-b35583b4b576';

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/v1/sos';
        $resolvedSos = $router->resolve(new Request());
        $this->assertSame('TowingController@requestSos', $resolvedSos['action']);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/v1/sos/radar';
        $resolvedRadar = $router->resolve(new Request());
        $this->assertSame('TowingController@radar', $resolvedRadar['action']);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = "/api/v1/sos/{$testUuid}";
        $resolvedShow = $router->resolve(new Request());
        $this->assertSame('TowingController@show', $resolvedShow['action']);
        $this->assertSame($testUuid, $resolvedShow['params']['uuid']);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = "/api/v1/sos/{$testUuid}/accept";
        $resolvedAccept = $router->resolve(new Request());
        $this->assertSame('TowingController@accept', $resolvedAccept['action']);
        $this->assertSame($testUuid, $resolvedAccept['params']['uuid']);

        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_SERVER['REQUEST_URI'] = "/api/v1/sos/{$testUuid}/telemetry";
        $resolvedTelemetry = $router->resolve(new Request());
        $this->assertSame('TowingController@telemetry', $resolvedTelemetry['action']);
        $this->assertSame($testUuid, $resolvedTelemetry['params']['uuid']);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = "/api/v1/sos/{$testUuid}/arrive";
        $resolvedArrive = $router->resolve(new Request());
        $this->assertSame('TowingController@arrive', $resolvedArrive['action']);
        $this->assertSame($testUuid, $resolvedArrive['params']['uuid']);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = "/api/v1/sos/{$testUuid}/complete";
        $resolvedComplete = $router->resolve(new Request());
        $this->assertSame('TowingController@complete', $resolvedComplete['action']);
        $this->assertSame($testUuid, $resolvedComplete['params']['uuid']);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = "/api/v1/sos/{$testUuid}/cancel";
        $resolvedCancel = $router->resolve(new Request());
        $this->assertSame('TowingController@cancel', $resolvedCancel['action']);
        $this->assertSame($testUuid, $resolvedCancel['params']['uuid']);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = "/api/v1/sos/{$testUuid}/rate";
        $resolvedRate = $router->resolve(new Request());
        $this->assertSame('TowingController@rate', $resolvedRate['action']);
        $this->assertSame($testUuid, $resolvedRate['params']['uuid']);
    }
}
