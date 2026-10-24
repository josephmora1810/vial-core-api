<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Core\DotEnv;
use App\Infrastructure\Database\Database;

(new DotEnv(__DIR__ . '/../.env'))->load();

$baseUrl = 'http://localhost:8088/api/v1';

$pdo = (new Database())->getConnection();
if (!$pdo) {
    echo "ERROR: No se pudo conectar a la base de datos MariaDB.\n";
    exit(1);
}

function httpCall(string $method, string $path, array $body = [], ?string $token = null, array $files = [], array $queryParams = []): array {
    global $baseUrl;

    $url = $baseUrl . $path;
    if (!empty($queryParams)) {
        $url .= '?' . http_build_query($queryParams);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = [];
    if ($token !== null) {
        $headers[] = "Authorization: Bearer {$token}";
    }

    if (!empty($files)) {
        $postFields = $body;
        foreach ($files as $field => $filePath) {
            $mime = mime_content_type($filePath) ?: 'image/png';
            $postFields[$field] = new CURLFile($filePath, $mime, basename($filePath));
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    } elseif (!empty($body)) {
        $json = json_encode($body);
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($json);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    $decoded = json_decode((string)$raw, true);

    return [
        'status' => $status,
        'body'   => $decoded ?? $raw,
        'error'  => $error,
    ];
}

$report = [];

function logStep(string $module, string $testName, string $endpoint, array $res, array $expectedStatuses): void {
    global $report;
    $isOk = in_array($res['status'], $expectedStatuses, true);
    $icon = $isOk ? '✅' : '❌';
    $msg = is_array($res['body']) ? ($res['body']['message'] ?? json_encode($res['body'])) : substr((string)$res['body'], 0, 80);

    echo sprintf("%s [%s] %-35s %-32s -> HTTP %d (Esperado: %s) | %s\n",
        $icon,
        strtoupper($module),
        substr($testName, 0, 35),
        $endpoint,
        $res['status'],
        implode('/', $expectedStatuses),
        substr((string)$msg, 0, 60)
    );

    $report[] = [
        'module'            => $module,
        'test'              => $testName,
        'endpoint'          => $endpoint,
        'status'            => $res['status'],
        'expected_statuses' => $expectedStatuses,
        'ok'                => $isOk,
        'response'          => $res['body'],
    ];
}

echo "======================================================================\n";
echo "🚀 PRUEBAS COMPLEMENTARIAS DE RUTAS EN API.PHP Y WEBSOCKETS\n";
echo "======================================================================\n\n";

// 1. Ping
$res = httpCall('GET', '/ping');
logStep('PUBLIC', 'Healthcheck Ping', '/ping', $res, [200]);

// 2. Teapot
$res = httpCall('GET', '/teapot');
logStep('PUBLIC', 'Easter egg Teapot', '/teapot', $res, [418]);

// 3. Catálogo de Vehículos
$res = httpCall('GET', '/vehicles/catalog/classes');
logStep('CATALOG', 'Clases de vehículos', '/vehicles/catalog/classes', $res, [200]);

$res = httpCall('GET', '/vehicles/catalog/subclasses');
logStep('CATALOG', 'Subclases de vehículos', '/vehicles/catalog/subclasses', $res, [200]);

$res = httpCall('GET', '/vehicles/catalog/brands');
logStep('CATALOG', 'Marcas de vehículos', '/vehicles/catalog/brands', $res, [200]);

$res = httpCall('GET', '/vehicles/catalog/models', [], null, [], ['brand_id' => 1]);
logStep('CATALOG', 'Modelos por marca', '/vehicles/catalog/models', $res, [200]);

// 4. Catálogos Financieros y Landing
$res = httpCall('GET', '/plans');
logStep('CATALOG', 'Planes vigentes', '/plans', $res, [200]);

$res = httpCall('GET', '/banks');
logStep('CATALOG', 'Bancos Sudeban', '/banks', $res, [200]);

$res = httpCall('GET', '/exchange-rates');
logStep('CATALOG', 'Tasas de cambio BCV', '/exchange-rates', $res, [200]);

$res = httpCall('GET', '/home-gallery');
logStep('PUBLIC', 'Galería Landing Page', '/home-gallery', $res, [200]);

// 5. Auth / Me / Perfil con usuario existente
$rand = bin2hex(random_bytes(2));
$testUserEmail = "test_user_{$rand}@vialcore.com";
httpCall('POST', '/register', [
    'email'               => $testUserEmail,
    'password'            => 'Password123!',
    'phone'               => '0414' . random_int(1000000, 9999999),
    'role'                => 'client',
    'terms_accepted'      => true,
    'identification_card' => 'V-' . random_int(10000000, 29000000),
    'names'               => 'Test',
    'surnames'            => 'User',
    'birth_date'          => '1992-04-12',
]);
$loginRes = httpCall('POST', '/login', ['email' => $testUserEmail, 'password' => 'Password123!']);
$userToken = $loginRes['body']['data']['token'] ?? null;

if ($userToken) {
    // /me
    $res = httpCall('GET', '/me', [], $userToken);
    logStep('AUTH', 'Datos de usuario /me', '/me', $res, [200]);

    // Direcciones: Crear y luego Delete
    $addrRes = httpCall('POST', '/addresses', [
        'name'      => 'Oficina Temporal',
        'address'   => 'Av Francisco de Miranda',
        'latitude'  => 10.4930,
        'longitude' => -66.8530,
        'is_favorite' => false,
    ], $userToken);
    $addrId = $addrRes['body']['data']['address']['id'] ?? ($addrRes['body']['data']['id'] ?? null);
    if ($addrId) {
        $delAddr = httpCall('DELETE', "/addresses/{$addrId}", [], $userToken);
        logStep('USER', 'Eliminar dirección guardada', "/addresses/{$addrId}", $delAddr, [200, 204]);
    }

    // Motivos de cancelación SOS
    $res = httpCall('GET', '/sos/cancellation-reasons', [], $userToken);
    logStep('TOWING', 'Motivos de cancelación de SOS', '/sos/cancellation-reasons', $res, [200]);

    // Historial SOS
    $res = httpCall('GET', '/sos/history', [], $userToken);
    logStep('TOWING', 'Historial de solicitudes SOS', '/sos/history', $res, [200]);

    // Orders
    $res = httpCall('GET', '/orders', [], $userToken);
    logStep('PAYMENTS', 'Listado de órdenes del usuario', '/orders', $res, [200]);
}

// 6. Admin token para rutas administrativas
$adminLogin = httpCall('POST', '/login', ['email' => 'admin_e2e_runner@vialtest.com', 'password' => 'AdminMasterPass123!']);
$adminToken = $adminLogin['body']['data']['token'] ?? null;

if ($adminToken) {
    // Admin Commissions
    $res = httpCall('GET', '/admin/commissions', [], $adminToken);
    logStep('ADMIN', 'Listado admin de comisiones', '/admin/commissions', $res, [200]);

    // Admin Documents (KYC Audit)
    $res = httpCall('GET', '/admin/documents', [], $adminToken);
    logStep('ADMIN', 'Auditoría KYC de documentos', '/admin/documents', $res, [200]);

    // Admin Home Gallery
    $res = httpCall('GET', '/admin/home-gallery', [], $adminToken);
    logStep('ADMIN', 'Gestión admin galería landing', '/admin/home-gallery', $res, [200]);

    // Admin Expiración de contratos vencidos
    $res = httpCall('POST', '/admin/contracts/expire-overdue', [], $adminToken);
    logStep('ADMIN', 'Expirar contratos vencidos', '/admin/contracts/expire-overdue', $res, [200]);

    // Sync tasas de cambio
    $res = httpCall('POST', '/exchange-rates/sync', [], $adminToken);
    logStep('ADMIN', 'Sincronizar tasas BCV', '/exchange-rates/sync', $res, [200, 429]);
}

// 7. Webhooks
// Pusher Webhook (channel_occupied)
$res = httpCall('POST', '/webhooks/pusher', [
    'events' => [
        [
            'name'    => 'channel_occupied',
            'channel' => 'private-cranes-radar',
        ],
    ],
]);
logStep('WEBHOOK', 'Webhook de Pusher', '/webhooks/pusher', $res, [200, 401]);

// 8. Flujo SOS Cancel con WebSockets
// Creamos una solicitud SOS y la cancelamos para verificar el broadcast de cancelacion
if ($userToken) {
    $planRow = $pdo->query("SELECT id FROM plans LIMIT 1")->fetch();
    $planId = (int)$planRow['id'];

    $modelRow = $pdo->query("SELECT id FROM vehicle_models LIMIT 1")->fetch();
    $modelId = (int)$modelRow['id'];

    $vPlate = 'CC' . random_int(100, 999) . 'XX';
    $vRes = httpCall('POST', '/vehicles', [
        'vehicle_model_id' => $modelId,
        'license_plate'    => $vPlate,
        'year'             => 2021,
        'color'            => 'Gris',
        'vin'              => '1HGCR2F83HC' . random_int(100000, 999999),
    ], $userToken);
    $vUuid = $vRes['body']['data']['vehicle']['uuid'] ?? null;

    if ($vUuid) {
        $sosReq = httpCall('POST', '/sos', [
            'vehicle_id'       => $vUuid,
            'breakdown_type'   => 'bateria',
            'pickup_address'   => 'Centro Lido, Chacao',
            'pickup_latitude'  => 10.491200,
            'pickup_longitude' => -66.852400,
        ], $userToken);
        $tempSosUuid = $sosReq['body']['data']['assistance']['uuid'] ?? null;

        if ($tempSosUuid) {
            // Consultar detalle SOS
            $detail = httpCall('GET', "/sos/{$tempSosUuid}", [], $userToken);
            logStep('TOWING', 'Consultar detalle SOS', "/sos/{$tempSosUuid}", $detail, [200]);

            // Cancelar SOS (dispara evento tow_service_cancelled vía Pusher)
            $cancel = httpCall('POST', "/sos/{$tempSosUuid}/cancel", [
                'reason' => 'Ya logré encender el vehículo con cables pasacorriente',
            ], $userToken);
            logStep('TOWING', 'Cancelar SOS en tiempo real', "/sos/{$tempSosUuid}/cancel", $cancel, [200]);
        }
    }
}

echo "\n======================================================================\n";
echo "📊 RESUMEN FINAL DE PRUEBAS COMPLEMENTARIAS\n";
echo "======================================================================\n";

$total = count($report);
$passed = count(array_filter($report, fn($r) => $r['ok']));
$failed = $total - $passed;

echo "Total endpoints evaluados: {$total}\n";
echo "Exitosos: {$passed}\n";
echo "Fallidos: {$failed}\n";
