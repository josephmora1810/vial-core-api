<?php

namespace Tests\Unit\Controllers;

use App\Application\Contracts\UseCases\ListUserContractsAndOrders;
use App\Application\Payments\UseCases\{HandleBancaribeWebhook, ProcessPayment};
use App\Controllers\PaymentController;
use App\Core\{Request, Response};
use App\Domain\Auth\{Entities\Role, ValueObjects\Password};
use App\Domain\Payments\Services\{BalanceGatewayInterface, ExchangeRateServiceInterface};
use App\Domain\Shared\ValueObjects\{Money, Uuid};
use App\Domain\Users\Entities\User;
use App\Domain\Users\ValueObjects\{
    BirthDate,
    Email,
    Names,
    NationalId,
    Phone,
    Surnames
};
use App\Infrastructure\Logging\IntegrationLogger;
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;

class PaymentControllerTest extends TestCase
{
    private ProcessPayment $processPayment;
    private HandleBancaribeWebhook $handleWebhook;
    private ListUserContractsAndOrders $listContractsAndOrders;
    private ExchangeRateServiceInterface $exchangeRateService;
    private BalanceGatewayInterface $balanceGateway;
    private IntegrationLogger $integrationLogger;
    private PaymentController $controller;

    protected function setUp(): void
    {
        Response::$shouldExit = false;
        $_POST = [];
        $_GET = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $this->processPayment = $this->createStub(ProcessPayment::class);
        $this->handleWebhook = $this->createStub(HandleBancaribeWebhook::class);
        $this->listContractsAndOrders = $this->createStub(ListUserContractsAndOrders::class);
        $this->exchangeRateService = $this->createStub(ExchangeRateServiceInterface::class);
        $this->balanceGateway = $this->createStub(BalanceGatewayInterface::class);
        $this->integrationLogger = $this->createStub(IntegrationLogger::class);

        $this->controller = new PaymentController(
            processPayment: $this->processPayment,
            handleWebhook: $this->handleWebhook,
            listContractsAndOrders: $this->listContractsAndOrders,
            exchangeRateService: $this->exchangeRateService,
            balanceGateway: $this->balanceGateway,
            integrationLogger: $this->integrationLogger
        );
    }

    protected function tearDown(): void
    {
        Response::$shouldExit = true;
        $_POST = [];
        $_GET = [];
    }

    private function createUser(int $id = 1, string $role = 'client'): User
    {
        return new User(
            id: $id,
            uuid: Uuid::generate(),
            names: new Names('Juan'),
            surnames: new Surnames('Pérez'),
            email: new Email('juan@example.com'),
            password: Password::fromPlainText('ClientSecret123!'),
            phone: new Phone('+584121234567'),
            walletBalance: new Money(0, 'VES'),
            identificationCard: new NationalId('V-12345678'),
            birthDate: new BirthDate('1990-01-01'),
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            consentAt: new DateTimeImmutable(),
            deletedAt: null,
            isActive: true,
            roles: [new Role(1, $role)]
        );
    }

    private function capture(callable $callback): array
    {
        ob_start();
        $callback();
        $output = (string)ob_get_clean();
        return json_decode($output, true) ?: [];
    }

    public function testExchangeRatesSuccess(): void
    {
        $this->exchangeRateService->method('getAllCurrentRates')->willReturn([
            'usd_venta'  => 36.50,
            'usd_compra' => 36.40,
            'rates'      => [
                ['currency' => 'USD', 'type' => 'venta', 'rate' => 36.50, 'date' => '2026-10-23'],
            ],
        ]);

        $res = $this->capture(fn() => $this->controller->exchangeRates(new Request()));
        $this->assertSame(200, $res['code']);
        $this->assertSame(36.50, $res['data']['usd_venta']);
    }

    public function testExchangeRatesFailureReturns503(): void
    {
        $this->exchangeRateService->method('getAllCurrentRates')
            ->willThrowException(new Exception('No hay tasas vigentes disponibles.', 503));

        $res = $this->capture(fn() => $this->controller->exchangeRates(new Request()));
        $this->assertSame(503, $res['code']);
    }

    public function testSyncExchangeRatesSuccess(): void
    {
        $this->exchangeRateService->method('fetchAndSaveTodayRates')->willReturn([
            ['currency' => 'USD', 'type' => 'venta', 'rate' => 36.50, 'date' => '2026-10-23'],
        ]);

        $res = $this->capture(fn() => $this->controller->syncExchangeRates(new Request()));
        $this->assertSame(200, $res['code']);
        $this->assertArrayHasKey('synced_rates', $res['data']);
    }

    public function testProcessPaymentUnauthorized(): void
    {
        $res = $this->capture(fn() => $this->controller->process(new Request()));
        $this->assertSame(401, $res['code']);
        $this->assertSame('No autorizado.', $res['message']);
    }

    public function testProcessPaymentMissingOrderIdReturns422(): void
    {
        $user = $this->createUser();
        $request = new Request();
        $request->setUser($user);

        $res = $this->capture(fn() => $this->controller->process($request));
        $this->assertSame(422, $res['code']);
        $this->assertArrayHasKey('order_id', $res['data']);
    }

    public function testProcessPaymentSuccess(): void
    {
        $user = $this->createUser();
        $_POST = [
            'order_id'       => 'ORD-2026-0001',
            'payment_method' => 'pago_movil',
            'reference'      => 'REF123456',
        ];
        $request = new Request();
        $request->setUser($user);

        $this->processPayment->method('execute')->willReturn([
            'payment' => [
                'uuid'           => 'p-1',
                'transaction_id' => 'TX-1',
                'payment_method' => 'pago_movil',
                'amount_ves'     => 1825.0,
                'status'         => 'paid',
                'paid_at'        => '2026-10-23 10:00:00',
                'bank_message'   => 'Aprobado',
            ],
            'order' => [
                'id'             => 'o-1',
                'order_number'   => 'ORD-2026-0001',
                'orderable_type' => 'service_contract',
                'orderable_id'   => 1,
                'amount_usd'     => 50.0,
                'exchange_rate'  => 36.5,
                'amount_ves'     => 1825.0,
                'bancaribe_ref'  => null,
                'status'         => 'paid',
                'paid_at'        => '2026-10-23 10:00:00',
                'created_at'     => '2026-10-23 09:00:00',
            ],
            'contract' => [
                'uuid'               => 'c-uuid-1',
                'status'             => 'ACTIVE',
                'remaining_services' => 3,
                'remaining_km'       => 100.0,
            ],
        ]);

        $res = $this->capture(fn() => $this->controller->process($request));
        $this->assertSame(200, $res['code']);
        $this->assertSame('success', $res['status']);
        $this->assertSame('TX-1', $res['data']['payment']['transaction_id']);
        $this->assertSame('c-uuid-1', $res['data']['contract']['uuid']);
    }

    public function testWebhookSuccess(): void
    {
        $_POST = ['order_id' => 10, 'status' => 'PAID'];
        $request = new Request();

        $this->handleWebhook->method('execute')->willReturn([
            'order' => [
                'id'             => 'o-1',
                'order_number'   => 'ORD-001',
                'orderable_type' => 'service_contract',
                'orderable_id'   => 1,
                'amount_usd'     => 50.0,
                'exchange_rate'  => 36.5,
                'amount_ves'     => 1825.0,
                'bancaribe_ref'  => null,
                'status'         => 'paid',
                'paid_at'        => '2026-10-23 10:00:00',
                'created_at'     => '2026-10-23 09:00:00',
            ],
            'contract' => ['uuid' => 'c-1'],
            'status'   => 'paid',
            'message'  => 'Liquidación confirmada exitosamente.',
        ]);

        $res = $this->capture(fn() => $this->controller->webhook($request));
        $this->assertSame(200, $res['code']);
        $this->assertSame('paid', $res['data']['status']);
    }

    public function testMyOrdersSuccess(): void
    {
        $user = $this->createUser();
        $request = new Request();
        $request->setUser($user);

        $this->listContractsAndOrders->method('listOrders')->willReturn([
            [
                'id'             => 'o-1',
                'order_number'   => 'ORD-001',
                'orderable_type' => 'service_contract',
                'orderable_id'   => 1,
                'amount_usd'     => 50.0,
                'exchange_rate'  => 36.5,
                'amount_ves'     => 1825.0,
                'bancaribe_ref'  => null,
                'status'         => 'paid',
                'paid_at'        => '2026-10-23 10:00:00',
                'created_at'     => '2026-10-23 09:00:00',
            ],
        ]);

        $res = $this->capture(fn() => $this->controller->myOrders($request));
        $this->assertSame(200, $res['code']);
        $this->assertCount(1, $res['data']);
        $this->assertSame('ORD-001', $res['data'][0]['order_number']);
    }

    public function testAdminBankBalancesSuccess(): void
    {
        $this->balanceGateway->method('getBalance')->willReturn([
            'success'  => true,
            'balance'  => 50000.0,
            'accounts' => [],
            'message'  => 'OK',
            'raw_data' => null,
        ]);

        $res = $this->capture(fn() => $this->controller->adminBankBalances(new Request()));
        $this->assertSame(200, $res['code']);
        $this->assertTrue($res['data']['success']);
    }

    public function testAdminBankBalancesFailureReturns502(): void
    {
        $this->balanceGateway->method('getBalance')->willReturn([
            'success' => false,
            'balance' => 0.0,
            'message' => 'Fallo de autenticación bancaria.',
        ]);

        $res = $this->capture(fn() => $this->controller->adminBankBalances(new Request()));
        $this->assertSame(502, $res['code']);
    }

    public function testAdminIntegrationLogsSuccess(): void
    {
        $_GET = ['service' => 'bancaribe', 'limit' => '10'];
        $request = new Request();

        $this->integrationLogger->method('readLogs')->willReturn([
            '2026-10-23 20:00:00 [bancaribe] Test log',
        ]);

        $res = $this->capture(fn() => $this->controller->adminIntegrationLogs($request));
        $this->assertSame(200, $res['code']);
        $this->assertSame(1, $res['data']['count']);
        $this->assertSame('bancaribe', $res['data']['filter_service']);
    }
}
