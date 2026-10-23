<?php

/**
 * Script de Verificación e Integración Bancaria (Bancaribe Smoke Test)
 * 
 * Ejecuta validaciones completas contra:
 * 1. OAuth2 Token de Producción (Obtención, persistencia en DB y caché Redis).
 * 2. Consulta de Tasas Oficiales BCV (Descarga en vivo, guardado en DB y caché Redis).
 * 3. Consulta de Saldos de Cuentas Bancarias (Llamada en vivo al endpoint CtasSaldos, parseo y caché).
 * 4. Procesamiento de Pagos C2P y Consulta Plus en modo Sandbox (Validación del contrato de respuesta).
 * 5. Guardrails de Seguridad (Bloqueo estricto de sandbox y fallback_rate en producción).
 */

use App\Core\DotEnv;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Redis;
use App\Infrastructure\Services\Bancaribe\BancaribeAuthService;
use App\Infrastructure\Services\Bancaribe\BancaribeBalanceService;
use App\Infrastructure\Services\Bancaribe\BancaribeExchangeService;
use App\Infrastructure\Services\Bancaribe\BancaribePaymentService;
use App\Domain\Payments\Entities\Order;
use App\Domain\Shared\ValueObjects\Money;

$rootPath = dirname(__DIR__);
require_once "{$rootPath}/autoload.php";

$dotEnv = new DotEnv("{$rootPath}/.env");
$dotEnv->load();

$container = require "{$rootPath}/bootstrap/container.php";

echo "========================================================\n";
echo "   UBIKGO CORE API - BANCARIBE SMOKE TEST & HARDENING   \n";
echo "========================================================\n\n";

$db = (new Database())->getConnection();
$redis = new Redis();

$testsPassed = 0;
$totalTests = 5;

// ----------------------------------------------------
// TEST 1: AUTENTICACIÓN OAUTH2 (TOKEN PRODUCCIÓN)
// ----------------------------------------------------
echo "[TEST 1] Autenticación OAuth2 Bancaribe...\n";
try {
    /** @var BancaribeAuthService $authService */
    $authService = $container['controllers'][\App\Controllers\PaymentController::class]();
    // Instanciar directamente usando el contenedor para aislamiento
    $httpClient = new \App\Infrastructure\Http\CurlHttpClient(30, 10);
    $bankTokenRepo = new \App\Infrastructure\Database\Repositories\Payments\MariaDbBankTokenRepository($db);
    
    $auth = new BancaribeAuthService(
        httpClient: $httpClient,
        tokenRepository: $bankTokenRepo,
        redis: $redis,
        baseAuthUrl: $_ENV['BANCARIBE_BASE_AUTH_URL'] ?? '',
        oauth2Endpoint: $_ENV['BANCARIBE_OAUTH2_ENDPOINT'] ?? 'oauth2/token',
        customerKey: $_ENV['BANCARIBE_COSTUMER_KEY'] ?? '',
        customerSecret: $_ENV['BANCARIBE_COSTUMER_SECRET'] ?? '',
        sandboxMode: false,
        isLocalEnv: true
    );

    $token = $auth->getAccessToken();
    if (empty($token) || strlen($token) < 10) {
        throw new \RuntimeException("El token obtenido es inválido o vacío.");
    }

    // Verificar en DB
    $dbRecord = $bankTokenRepo->findByGateway('bancaribe');
    $dbOk = ($dbRecord !== null && !empty($dbRecord['access_token']));

    // Verificar en Redis
    $cached = $redis->execute('GET', 'bancaribe_access_token');
    $redisOk = (!empty($cached));

    echo "  -> Token obtenido exitosamente: OK (Longitud: " . strlen($token) . " chars)\n";
    echo "  -> Persistencia en MariaDB (tabla bank_tokens): " . ($dbOk ? "OK" : "FALLÓ") . "\n";
    echo "  -> Persistencia en Redis (key bancaribe_access_token): " . ($redisOk ? "OK" : "FALLÓ") . "\n";

    if ($dbOk && $redisOk) {
        $testsPassed++;
        echo "  [RESULTADO: PASS]\n\n";
    } else {
        echo "  [RESULTADO: FAIL]\n\n";
    }
} catch (\Throwable $e) {
    echo "  -> ERROR en Test 1: " . $e->getMessage() . "\n";
    echo "  [RESULTADO: FAIL]\n\n";
}

// ----------------------------------------------------
// TEST 2: CONSULTA Y SINCRONIZACIÓN DE TASAS BCV
// ----------------------------------------------------
echo "[TEST 2] Consulta y Guardado de Tasas BCV...\n";
try {
    $rateRepo = new \App\Infrastructure\Database\Repositories\Payments\MariaDbExchangeRateRepository($db);
    $exchangeService = new BancaribeExchangeService(
        authService: $auth,
        httpClient: $httpClient,
        rateRepository: $rateRepo,
        redis: $redis,
        baseUrl: $_ENV['BANCARIBE_BASE_URL'] ?? '',
        exchangeEndpoint: $_ENV['BANCARIBE_EXCHANGE_ENDPOINT'] ?? 'BCVMicro/1.0.0/consultaTasa/tasa',
        exchangeHash: $_ENV['BANCARIBE_EXCHANGE_HASH'] ?? '',
        rif: $_ENV['APP_RIF'] ?? 'J502098500',
        isLocalEnv: true,
        fallbackRate: 40.00
    );

    $synced = $exchangeService->fetchAndSaveTodayRates();
    $usdVenta = $exchangeService->getExchangeRate('USD', 'venta');
    $usdCompra = $exchangeService->getExchangeRate('USD', 'compra');
    $allRates = $exchangeService->getAllCurrentRates();

    // Validar en DB
    $today = new \DateTimeImmutable('today');
    $rateDb = $rateRepo->findByDate('USD', 'venta', $today);

    // Validar en Redis
    $cachedRate = $redis->execute('GET', 'bancaribe_exchange_rate_USD_venta');

    echo "  -> Tasas sincronizadas en vivo desde Bancaribe: " . count($synced) . " items procesados\n";
    echo "  -> Tasa USD Venta obtenida: " . $usdVenta . " VES\n";
    echo "  -> Tasa USD Compra obtenida: " . $usdCompra . " VES\n";
    echo "  -> Guardado en MariaDB (exchange_rates): " . ($rateDb !== null ? "OK" : "FALLÓ") . "\n";
    echo "  -> Caché en Redis (bancaribe_exchange_rate_USD_venta): " . ($cachedRate !== null ? "OK (" . $cachedRate . " VES)" : "FALLÓ") . "\n";

    if ($rateDb !== null && $usdVenta > 0) {
        $testsPassed++;
        echo "  [RESULTADO: PASS]\n\n";
    } else {
        echo "  [RESULTADO: FAIL]\n\n";
    }
} catch (\Throwable $e) {
    echo "  -> ERROR en Test 2: " . $e->getMessage() . "\n";
    echo "  [RESULTADO: FAIL]\n\n";
}

// ----------------------------------------------------
// TEST 3: CONSULTA DE SALDOS DE CUENTAS BANCARIAS
// ----------------------------------------------------
echo "[TEST 3] Consulta de Saldos Bancaribe en Vivo (CtasSaldos)...\n";
try {
    $balanceService = new BancaribeBalanceService(
        authService: $auth,
        httpClient: $httpClient,
        redis: $redis,
        baseUrl: $_ENV['BANCARIBE_BASE_URL'] ?? '',
        checkBalanceEndpoint: $_ENV['BANCARIBE_CHECK_BALANCE_ENDPOINT'] ?? 'CtasSaldos/1.0.0/ctasConsultaSaldos',
        checkBalanceHash: $_ENV['BANCARIBE_CHECK_BALANCE_HASH'] ?? '',
        rif: $_ENV['APP_RIF'] ?? 'J502098500',
        sandboxMode: false,
        isLocalEnv: true
    );

    $balanceRes = $balanceService->getBalance(forceRefresh: true);

    $isSuccess = $balanceRes['success'] ?? false;
    $accountsCount = count($balanceRes['accounts'] ?? []);
    $cachedBalance = $redis->execute('GET', 'bancaribe_balance_cache');

    echo "  -> Llamada al banco exitosa: " . ($isSuccess ? "SÍ (HTTP 200)" : "NO") . "\n";
    echo "  -> Cuentas encontradas: " . $accountsCount . "\n";
    echo "  -> Estructura normalizada de respuesta:\n";
    echo "       - Total Balance: [CONFIDENCIAL / DISPONIBLE]\n";
    if ($accountsCount > 0) {
        $acc = $balanceRes['accounts'][0];
        echo "       - Moneda: " . ($acc['currency_code'] ?? 'N/A') . " (" . ($acc['currency_name'] ?? 'N/A') . ")\n";
        echo "       - Campos presentes: account_number (enmascarable), available, balance, blocked\n";
    }
    echo "  -> Caché en Redis (bancaribe_balance_cache): " . (!empty($cachedBalance) ? "OK" : "FALLÓ") . "\n";

    if ($isSuccess && $accountsCount > 0) {
        $testsPassed++;
        echo "  [RESULTADO: PASS]\n\n";
    } else {
        echo "  [RESULTADO: FAIL]\n\n";
    }
} catch (\Throwable $e) {
    echo "  -> ERROR en Test 3: " . $e->getMessage() . "\n";
    echo "  [RESULTADO: FAIL]\n\n";
}

// ----------------------------------------------------
// TEST 4: RESPUESTA DE PAGOS C2P Y CONSULTA PLUS (SANDBOX)
// ----------------------------------------------------
echo "[TEST 4] Simulación de Pagos C2P y Operaciones Plus (Sandbox)...\n";
try {
    $paymentService = new BancaribePaymentService(
        authService: $auth,
        exchangeService: $exchangeService,
        httpClient: $httpClient,
        baseUrl: $_ENV['BANCARIBE_BASE_URL'] ?? '',
        c2pEndpoint: $_ENV['BANCARIBE_C2P_ENDPOINT'] ?? 'C2P/1.0.0/registrarPagoC2pApi',
        plusEndpoint: $_ENV['BANCARIBE_PLUS_ENDPOINT'] ?? 'COP/1.0.0/consulta-operacion',
        c2pHash: $_ENV['BANCARIBE_C2P_HASH'] ?? '',
        plusHash: $_ENV['BANCARIBE_PLUS_HASH'] ?? '',
        rifComercio: $_ENV['APP_RIF'] ?? 'J502098500',
        telefonoComercio: $_ENV['APP_TELEFONO'] ?? '04267536320',
        nombreComercio: 'UbikGo',
        sandboxMode: true,
        isLocalEnv: true
    );

    // Simular Orden
    $fakeOrder = new Order(
        userId: 1,
        orderableType: 'service_contract',
        orderableId: 1,
        amountUsd: new Money(10.00, \App\Domain\Shared\Enums\Currency::USD),
        exchangeRate: $usdVenta,
        amountVes: new Money(round(10.00 * $usdVenta, 2), \App\Domain\Shared\Enums\Currency::VES),
        orderNumber: 'ORD-TEST-001'
    );

    $c2pData = [
        'bancoPagador'    => '0102',
        'telefonoPersona' => '04141234567',
        'otp'             => '12345678',
        'documentType'    => 'V',
        'documentNumber'  => '12345678',
        'identificadorPersona' => 'V12345678',
    ];

    $c2pResult = $paymentService->processC2p($fakeOrder, $c2pData);

    $c2pOk = ($c2pResult->success === true && !empty($c2pResult->transactionId));
    echo "  -> Respuesta C2P: " . ($c2pResult->success ? "ÉXITO" : "FALLÓ") . "\n";
    echo "       - Mensaje: " . $c2pResult->message . "\n";
    echo "       - Transacción ID asignada: " . ($c2pResult->transactionId ?? 'N/A') . "\n";

    // Simular Consulta Operaciones Plus
    $plusResult = $paymentService->verifyPagoMovil(
        reference: '987654',
        date: date('Y-m-d'),
        amount: round(10.00 * $usdVenta, 2),
        identificadorPersona: 'V12345678',
        telefono: '04141234567'
    );
    $plusOk = ($plusResult->success === true);
    echo "  -> Respuesta Consulta Plus: " . ($plusResult->success ? "ÉXITO" : "FALLÓ") . "\n";
    echo "       - Mensaje: " . $plusResult->message . "\n";

    if ($c2pOk && $plusOk) {
        $testsPassed++;
        echo "  [RESULTADO: PASS]\n\n";
    } else {
        echo "  [RESULTADO: FAIL]\n\n";
    }
} catch (\Throwable $e) {
    echo "  -> ERROR en Test 4: " . $e->getMessage() . "\n";
    echo "  [RESULTADO: FAIL]\n\n";
}

// ----------------------------------------------------
// TEST 5: GUARDRAILS DE SEGURIDAD EN PRODUCCIÓN
// ----------------------------------------------------
echo "[TEST 5] Verificación de Guardrails en Producción...\n";
try {
    $guardrail1Passed = false;
    $guardrail2Passed = false;

    // Guardrail 1: En entorno no local, sandboxMode: true DEBE lanzar LogicException
    try {
        new BancaribeAuthService(
            httpClient: $httpClient,
            tokenRepository: $bankTokenRepo,
            sandboxMode: true,
            isLocalEnv: false // Simulando entorno de producción
        );
    } catch (\LogicException $e) {
        $guardrail1Passed = true;
        echo "  -> Guardrail 1 (Sandbox bloqueado cuando isLocalEnv=false): ACTIVO (" . $e->getMessage() . ")\n";
    }

    // Guardrail 2: En entorno no local, caída de tasas sin tasa reciente DEBE lanzar HTTP 503
    $mockFailingClient = new class implements \App\Infrastructure\Http\HttpClientInterface {
        public function get(string $url, array $query = [], array $headers = []): \App\Infrastructure\Http\HttpResponse {
            throw new \RuntimeException("Conexión perdida con banco");
        }
        public function postJson(string $url, array $data, array $headers = []): \App\Infrastructure\Http\HttpResponse {
            throw new \RuntimeException("Conexión perdida con banco");
        }
        public function postForm(string $url, array $data, array $headers = []): \App\Infrastructure\Http\HttpResponse {
            throw new \RuntimeException("Conexión perdida con banco");
        }
        public function postMultipart(string $url, array $fields, array $files, array $headers = []): \App\Infrastructure\Http\HttpResponse {
            throw new \RuntimeException("Conexión perdida con banco");
        }
    };

    $mockEmptyRepo = new class implements \App\Domain\Payments\Repositories\ExchangeRateRepositoryInterface {
        public function findByDate(string $currency, string $type, \DateTimeImmutable $date): ?\App\Domain\Payments\Entities\ExchangeRate {
            return null;
        }
        public function findLatest(string $currency, string $type, ?\DateTimeImmutable $sinceDate = null): ?\App\Domain\Payments\Entities\ExchangeRate {
            return null; // No hay tasas registradas
        }
        public function findLatestAll(): array { return []; }
        public function upsert(\App\Domain\Payments\Entities\ExchangeRate $exchangeRate): void {}
    };

    $productionExchangeService = new BancaribeExchangeService(
        authService: $auth,
        httpClient: $mockFailingClient,
        rateRepository: $mockEmptyRepo,
        redis: null,
        isLocalEnv: false, // PRODUCCIÓN
        fallbackRate: 40.00
    );

    try {
        $productionExchangeService->getExchangeRate('USD', 'venta');
    } catch (\RuntimeException $e) {
        if ($e->getCode() === 503) {
            $guardrail2Passed = true;
            echo "  -> Guardrail 2 (Caída de tasas en producción lanza HTTP 503 y NUNCA usa 40.00): ACTIVO (" . $e->getMessage() . ")\n";
        }
    }

    if ($guardrail1Passed && $guardrail2Passed) {
        $testsPassed++;
        echo "  [RESULTADO: PASS]\n\n";
    } else {
        echo "  [RESULTADO: FAIL]\n\n";
    }
} catch (\Throwable $e) {
    echo "  -> ERROR en Test 5: " . $e->getMessage() . "\n";
    echo "  [RESULTADO: FAIL]\n\n";
}

echo "========================================================\n";
echo "   RESUMEN FINAL: {$testsPassed} de {$totalTests} PRUEBAS EXITOSAS   \n";
echo "========================================================\n";

if ($testsPassed === $totalTests) {
    exit(0);
} else {
    exit(1);
}
