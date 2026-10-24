<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Core\DotEnv;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Infrastructure\Database\Database;

(new DotEnv(__DIR__ . '/../.env'))->load();

$baseUrl = 'http://localhost:8088/api/v1';
$ocrImagePath = 'C:\\Users\\josep\\OneDrive\\Pictures\\Artgoritmo\\paint.png';

$pdo = (new Database())->getConnection();
if (!$pdo) {
    echo "ERROR: No se pudo conectar a la base de datos MariaDB.\n";
    exit(1);
}

$report = [];

function httpCall(
    string $method,
    string $endpoint,
    array $body = [],
    ?string $token = null,
    array $files = [],
    array $query = []
): array {
    global $baseUrl;

    $url = $baseUrl . $endpoint;
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
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
        // Multipart
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

function logStep(string $module, string $testName, string $endpoint, array $res, int $expectedStatus): void {
    global $report;
    $isOk = ($res['status'] === $expectedStatus) || ($expectedStatus === 200 && in_array($res['status'], [200, 201]));
    $icon = $isOk ? '✅' : '❌';
    $msg = is_array($res['body']) ? ($res['body']['message'] ?? json_encode($res['body'])) : substr((string)$res['body'], 0, 80);

    echo sprintf("%s [%s] %-35s %-32s -> HTTP %d (Esperado: %d) | %s\n",
        $icon,
        strtoupper($module),
        substr($testName, 0, 35),
        $endpoint,
        $res['status'],
        $expectedStatus,
        substr((string)$msg, 0, 60)
    );

    $report[] = [
        'module'          => $module,
        'test'            => $testName,
        'endpoint'        => $endpoint,
        'status'          => $res['status'],
        'expected_status' => $expectedStatus,
        'ok'              => $isOk,
        'response'        => $res['body'],
    ];
}

echo "======================================================================\n";
echo "🚀 INICIO DE PRUEBAS EN CALIENTE DE LA API REST VIAL-CORE-API\n";
echo "Servidor: {$baseUrl}\n";
echo "Imagen OCR: {$ocrImagePath}\n";
echo "======================================================================\n\n";

// ======================================================================
// FASE 1: CLIENTE (CLIENT)
// ======================================================================
echo "--- FASE 1: CLIENTE (Registro, Login, Vehículo, Contrato, SOS, KYC) ---\n";

$rand = bin2hex(random_bytes(3));
$clientEmail = "cliente_{$rand}@testvial.com";
$clientPass = "Password123!";
$clientPhone = "0414" . random_int(1000000, 9999999);
$clientNationalId = "V-" . random_int(15000000, 30000000);

// 1. Registro
$res = httpCall('POST', '/register', [
    'email'               => $clientEmail,
    'password'            => $clientPass,
    'phone'               => $clientPhone,
    'role'                => 'client',
    'terms_accepted'      => true,
    'identification_card' => $clientNationalId,
    'names'               => 'Carlos Alberto',
    'surnames'            => 'Perez Rodriguez',
    'birth_date'          => '1990-05-15',
]);
logStep('CLIENT', 'Registro de nuevo cliente', '/register', $res, 201);

// 2. Login
$res = httpCall('POST', '/login', [
    'email'    => $clientEmail,
    'password' => $clientPass,
]);
logStep('CLIENT', 'Inicio de sesión', '/login', $res, 200);
$clientToken = $res['body']['data']['token'] ?? null;
$clientRefreshToken = $res['body']['data']['refresh_token'] ?? null;

// 3. Refresh Token
$res = httpCall('POST', '/refresh-token', [
    'refresh_token' => $clientRefreshToken,
]);
logStep('CLIENT', 'Refresco de token JWT', '/refresh-token', $res, 200);
if (!empty($res['body']['data']['token'])) {
    $clientToken = $res['body']['data']['token'];
    $clientRefreshToken = $res['body']['data']['refresh_token'];
}

// 4. Scan OCR de vehículo (usando la imagen en caliente)
if (file_exists($ocrImagePath)) {
    $res = httpCall('POST', '/vehicles/scan', [], $clientToken, ['document' => $ocrImagePath]);
    logStep('CLIENT', 'Escaneo OCR de Carnet (Microservicio)', '/vehicles/scan', $res, 200);
} else {
    echo "⚠️ Imagen de OCR no encontrada en $ocrImagePath, omitiendo scan multipart\n";
}

// 5. Registrar Vehículo
$vehiclePlate = "AB" . random_int(100, 999) . "XY";
$vehicleNiv = "1HGCR2F83HA" . random_int(100000, 999999);
$res = httpCall('POST', '/vehicles', [
    'plate_number'      => $vehiclePlate,
    'niv'               => $vehicleNiv,
    'year'              => 2022,
    'color'             => 'Plata',
    'brand_name'        => 'Toyota',
    'model_name'        => 'Corolla',
    'vehicle_class_id'  => 1,
], $clientToken);
logStep('CLIENT', 'Registro manual de vehículo', '/vehicles', $res, 201);
$vehicleUuid = $res['body']['data']['uuid'] ?? ($res['body']['data']['id'] ?? null);

// Activamos el vehículo en MariaDB para habilitar operaciones inmediatas de auxilio vial
if ($vehicleUuid) {
    $pdo->exec("UPDATE vehicles SET status = 'ACTIVE', is_verified = 1 WHERE uuid = '{$vehicleUuid}'");
}

// 6. Consultar y Detalle de Vehículo
$res = httpCall('GET', '/vehicles', [], $clientToken);
logStep('CLIENT', 'Listar vehículos del cliente', '/vehicles', $res, 200);

if ($vehicleUuid) {
    $res = httpCall('GET', "/vehicles/{$vehicleUuid}", [], $clientToken);
    logStep('CLIENT', 'Consultar detalle de vehículo', "/vehicles/{$vehicleUuid}", $res, 200);
}

// 7. Guardar Dirección
$res = httpCall('POST', '/addresses', [
    'label'        => 'Casa Altamira',
    'address_line' => 'Av. San Juan Bosco, Edif. Parque, Apto 4B',
    'latitude'     => 10.495000,
    'longitude'    => -66.850000,
    'is_favorite'  => true,
], $clientToken);
logStep('CLIENT', 'Guardar dirección favorita', '/addresses', $res, 201);
$addressId = $res['body']['data']['id'] ?? null;

// 8. Listar Direcciones Guardadas
$res = httpCall('GET', '/addresses', [], $clientToken);
logStep('CLIENT', 'Listar direcciones guardadas', '/addresses', $res, 200);

// 9. Comprar Contrato (Checkout)
$plansRes = httpCall('GET', '/plans');
$planId = $plansRes['body']['data']['plans'][0]['id'] ?? '01a16a7c-c48c-7264-8328-02bd523f8749';

$res = httpCall('POST', '/checkout', [
    'plan_id'    => $planId,
    'vehicle_id' => $vehicleUuid,
], $clientToken);
logStep('CLIENT', 'Checkout compra de contrato', '/checkout', $res, 201);
$orderNumber = $res['body']['data']['order']['order_number'] ?? null;
$contractUuid = $res['body']['data']['contract']['uuid'] ?? ($res['body']['data']['contract']['id'] ?? null);

// 10. Pagar el Contrato (C2P Sandbox)
if ($orderNumber) {
    $res = httpCall('POST', '/payments/process', [
        'order_number'    => $orderNumber,
        'payment_method'  => 'C2P',
        'bancoPagador'    => '0102',
        'telefonoPersona' => $clientPhone,
        'otp'             => '123456',
    ], $clientToken);
    logStep('CLIENT', 'Pago C2P de contrato en Sandbox', '/payments/process', $res, 200);
}

// 11. Consultar Mis Contratos
$res = httpCall('GET', '/contracts', [], $clientToken);
logStep('CLIENT', 'Consultar contratos del cliente', '/contracts', $res, 200);

// 12. Cotizar Servicio SOS
$res = httpCall('POST', '/sos/quote', [
    'vehicle_id' => $vehicleUuid,
    'origin_lat' => 10.495000,
    'origin_lng' => -66.850000,
    'dest_lat'   => 10.480000,
    'dest_lng'   => -66.870000,
], $clientToken);
logStep('CLIENT', 'Cotizar auxilio vial SOS', '/sos/quote', $res, 200);

// 13. Solicitar Servicio SOS
$res = httpCall('POST', '/sos', [
    'vehicle_id'          => $vehicleUuid,
    'origin_lat'          => 10.495000,
    'origin_lng'          => -66.850000,
    'origin_address'      => 'Av San Juan Bosco, Altamira',
    'dest_lat'            => 10.480000,
    'dest_lng'            => -66.870000,
    'dest_address'        => 'Taller Mecánico Bello Monte',
    'failure_description' => 'Falla en alternador y batería descargada',
], $clientToken);
logStep('CLIENT', 'Solicitar servicio SOS', '/sos', $res, 201);
$sosUuid = $res['body']['data']['service']['id'] ?? null;

// 14. Consultar Servicio SOS Activo
$res = httpCall('GET', '/sos/active', [], $clientToken);
logStep('CLIENT', 'Consultar servicio SOS activo', '/sos/active', $res, 200);

// 15. Canje de contrato por servicio
if ($contractUuid) {
    $res = httpCall('POST', "/contracts/{$contractUuid}/redeem", [], $clientToken);
    logStep('CLIENT', 'Canjear servicio con contrato', "/contracts/{$contractUuid}/redeem", $res, 200);
}

// 16. Subida de documento KYC
if (file_exists($ocrImagePath)) {
    $res = httpCall('POST', '/documents/upload', [
        'document_type' => 'identity_card',
    ], $clientToken, ['document' => $ocrImagePath]);
    logStep('CLIENT', 'Subida de documento KYC', '/documents/upload', $res, 200);
}

// 17. Consultar Estado KYC
$res = httpCall('GET', '/documents/kyc-status', [], $clientToken);
logStep('CLIENT', 'Consultar estado KYC', '/documents/kyc-status', $res, 200);

// 18. Subida de Avatar
if (file_exists($ocrImagePath)) {
    $res = httpCall('POST', '/users/avatar', [], $clientToken, ['avatar' => $ocrImagePath]);
    logStep('CLIENT', 'Subida de Avatar de usuario', '/users/avatar', $res, 200);
}

echo "\n";

// ======================================================================
// FASE 2: ADMINISTRADOR (ADMIN)
// ======================================================================
echo "--- FASE 2: ADMINISTRADOR (SQL Directo, Logs, Métricas, Moderación) ---\n";

$adminEmail = "admin_master@ubikgo.com";
$adminPass = "Admin123456!";
$adminHash = password_hash($adminPass, PASSWORD_BCRYPT);
$adminUuid = Uuid::generate()->getValue();

// Crear Administrador por SQL directo
$stmt = $pdo->prepare("
    INSERT INTO users (uuid, names, surnames, identification_card, birth_date, phone, email, password, is_active)
    VALUES (:uuid, 'Super', 'Admin', 'V-00000001', '1985-01-01', '04120000000', :email, :password, 1)
    ON DUPLICATE KEY UPDATE password = :password2, is_active = 1
");
$stmt->execute([
    'uuid'      => $adminUuid,
    'email'     => $adminEmail,
    'password'  => $adminHash,
    'password2' => $adminHash,
]);

$adminUserRow = $pdo->query("SELECT id FROM users WHERE email = '{$adminEmail}'")->fetch();
$adminUserId = (int)$adminUserRow['id'];

$roleRow = $pdo->query("SELECT id FROM roles WHERE name = 'admin'")->fetch();
$adminRoleId = (int)$roleRow['id'];

$pdo->exec("
    INSERT INTO role_user (role_id, user_id) 
    SELECT {$adminRoleId}, {$adminUserId}
    WHERE NOT EXISTS (SELECT 1 FROM role_user WHERE role_id = {$adminRoleId} AND user_id = {$adminUserId})
");

// 19. Login Administrador
$res = httpCall('POST', '/login', [
    'email'    => $adminEmail,
    'password' => $adminPass,
]);
logStep('ADMIN', 'Inicio de sesión de Administrador', '/login', $res, 200);
$adminToken = $res['body']['data']['token'] ?? null;

// 20. Ver Logs de Integración
$res = httpCall('GET', '/admin/integrations/logs', [], $adminToken);
logStep('ADMIN', 'Consultar logs de integración', '/admin/integrations/logs', $res, 200);

// 21. Consultar Saldos Bancarios
$res = httpCall('GET', '/admin/bank/balances', [], $adminToken);
logStep('ADMIN', 'Consultar saldos bancarios Bancaribe', '/admin/bank/balances', $res, 200);

// 22. Suspender Usuario (Soft delete / Moderación)
$clientUserRow = $pdo->query("SELECT id FROM users WHERE email = '{$clientEmail}'")->fetch();
$clientDbId = (int)$clientUserRow['id'];

$res = httpCall('POST', "/admin/users/{$clientDbId}/suspend", [
    'reason' => 'Verificación rutinaria de cuenta por seguridad',
], $adminToken);
logStep('ADMIN', 'Suspender usuario (Moderación)', "/admin/users/{$clientDbId}/suspend", $res, 200);

// 23. Reactivar Usuario
$res = httpCall('POST', "/admin/users/{$clientDbId}/activate", [], $adminToken);
logStep('ADMIN', 'Reactivar usuario', "/admin/users/{$clientDbId}/activate", $res, 200);

// 24. Crear Segundo Administrador por SQL Directo
$admin2Email = "admin_secundario@ubikgo.com";
$admin2Pass = "AdminSecundario987!";
$admin2Hash = password_hash($admin2Pass, PASSWORD_BCRYPT);
$admin2Uuid = Uuid::generate()->getValue();

$stmt = $pdo->prepare("
    INSERT INTO users (uuid, names, surnames, identification_card, birth_date, phone, email, password, is_active)
    VALUES (:uuid, 'Segundo', 'Administrador', 'V-00000002', '1988-02-02', '04120000002', :email, :password, 1)
    ON DUPLICATE KEY UPDATE password = :password2, is_active = 1
");
$stmt->execute([
    'uuid'      => $admin2Uuid,
    'email'     => $admin2Email,
    'password'  => $admin2Hash,
    'password2' => $admin2Hash,
]);

$admin2UserRow = $pdo->query("SELECT id FROM users WHERE email = '{$admin2Email}'")->fetch();
$admin2UserId = (int)$admin2UserRow['id'];
$pdo->exec("
    INSERT INTO role_user (role_id, user_id) 
    SELECT {$adminRoleId}, {$admin2UserId}
    WHERE NOT EXISTS (SELECT 1 FROM role_user WHERE role_id = {$adminRoleId} AND user_id = {$admin2UserId})
");

$res = httpCall('POST', '/login', [
    'email'    => $admin2Email,
    'password' => $admin2Pass,
]);
logStep('ADMIN', 'Login de segundo Administrador creado', '/login', $res, 200);

echo "\n";

// ======================================================================
// FASE 3: VENDEDORA (SELLER)
// ======================================================================
echo "--- FASE 3: VENDEDORA (Comisiones, Afiliaciones, Contratos, Retiros) ---\n";

$sellerEmail = "vendedora_{$rand}@testvial.com";
$sellerPass = "SellerPass123!";
$sellerHash = password_hash($sellerPass, PASSWORD_BCRYPT);
$sellerUuid = Uuid::generate()->getValue();
$sellerCi = "V-" . random_int(20000000, 35000000);

// Creamos usuario vendedora
$stmt = $pdo->prepare("
    INSERT INTO users (uuid, names, surnames, identification_card, birth_date, phone, email, password, is_active)
    VALUES (:uuid, 'Maria', 'Vendedora Gomez', :ci, '1995-04-10', '04169998877', :email, :password, 1)
    ON DUPLICATE KEY UPDATE password = :password2, is_active = 1
");
$stmt->execute([
    'uuid'      => $sellerUuid,
    'ci'        => $sellerCi,
    'email'     => $sellerEmail,
    'password'  => $sellerHash,
    'password2' => $sellerHash,
]);

$sellerRow = $pdo->query("SELECT id FROM users WHERE email = '{$sellerEmail}'")->fetch();
$sellerId = (int)$sellerRow['id'];

$sellerRoleRow = $pdo->query("SELECT id FROM roles WHERE name = 'seller'")->fetch();
$sellerRoleId = (int)$sellerRoleRow['id'];

$pdo->exec("
    INSERT INTO role_user (role_id, user_id) 
    SELECT {$sellerRoleId}, {$sellerId}
    WHERE NOT EXISTS (SELECT 1 FROM role_user WHERE role_id = {$sellerRoleId} AND user_id = {$sellerId})
");

// Crear perfil en seller_profiles
$pdo->exec("
    INSERT INTO seller_profiles (user_id, commission_rate, account_type)
    VALUES ({$sellerId}, 12.50, 'CORRIENTE')
    ON DUPLICATE KEY UPDATE commission_rate = 12.50
");

// 25. Login Vendedora
$res = httpCall('POST', '/login', [
    'email'    => $sellerEmail,
    'password' => $sellerPass,
]);
logStep('SELLER', 'Inicio de sesión de Vendedora', '/login', $res, 200);
$sellerToken = $res['body']['data']['token'] ?? null;
$sellerRefreshToken = $res['body']['data']['refresh_token'] ?? null;

// 26. Refresh Token Vendedora
$res = httpCall('POST', '/refresh-token', [
    'refresh_token' => $sellerRefreshToken,
]);
logStep('SELLER', 'Refrescar token de Vendedora', '/refresh-token', $res, 200);
if (!empty($res['body']['data']['token'])) {
    $sellerToken = $res['body']['data']['token'];
    $sellerRefreshToken = $res['body']['data']['refresh_token'];
}

// 27. Consultar Perfil de Vendedora
$res = httpCall('GET', '/seller/profile', [], $sellerToken);
logStep('SELLER', 'Consultar perfil de Vendedora', '/seller/profile', $res, 200);

// 28. Actualizar Cuenta Bancaria (Banco de Venezuela 0102 con número que empieza con 0102)
$res = httpCall('POST', '/seller/bank-account', [
    'bank_id'             => 1, // Banco de Venezuela (0102)
    'account_number'      => '01020150991234567890',
    'account_type'        => 'CORRIENTE',
    'account_holder_name' => 'Maria Gomez',
    'account_holder_id'   => $sellerCi,
    'phone_pago_movil'    => '04169998877',
], $sellerToken);
logStep('SELLER', 'Actualizar cuenta bancaria de comisiones', '/seller/bank-account', $res, 200);

// 29. Vendedora Registra un Nuevo Cliente
$sellerClientEmail = "cliente_seller_{$rand}@testvial.com";
$sellerClientCi = "V-" . random_int(18000000, 32000000);
$res = httpCall('POST', '/seller/clients', [
    'email'               => $sellerClientEmail,
    'phone'               => '0412' . random_int(1000000, 9999999),
    'identification_card' => $sellerClientCi,
    'names'               => 'Andres',
    'surnames'            => 'Bello',
    'birth_date'          => '1992-08-20',
], $sellerToken);
logStep('SELLER', 'Registrar nuevo cliente por vendedora', '/seller/clients', $res, 201);
$newClientId = $res['body']['data']['client']['uuid'] ?? ($res['body']['data']['client']['id'] ?? null);

// 30. Vendedora Registra Vehículo para el Cliente
$sellerPlate = 'AF' . random_int(100, 999) . 'JK';
$sellerNiv = '1HGCR2F83HB' . random_int(100000, 999999);
$res = httpCall('POST', '/seller/vehicles', [
    'client_id'        => $newClientId,
    'plate_number'     => $sellerPlate,
    'niv'              => $sellerNiv,
    'year'             => 2020,
    'color'            => 'Blanco',
    'brand_name'       => 'Chevrolet',
    'model_name'       => 'Cruze',
    'vehicle_class_id' => 1,
], $sellerToken);
logStep('SELLER', 'Registrar vehículo para cliente', '/seller/vehicles', $res, 201);
$sellerVehicleUuid = $res['body']['data']['vehicle']['uuid'] ?? ($res['body']['data']['vehicle']['id'] ?? null);

// 31. Vendedora Crea Contrato para el Cliente
$res = httpCall('POST', '/seller/contracts', [
    'client_id'  => $newClientId,
    'plan_id'    => $planId,
    'vehicle_id' => $sellerVehicleUuid,
], $sellerToken);
logStep('SELLER', 'Generar contrato para cliente', '/seller/contracts', $res, 201);
$sellerOrderNumber = $res['body']['data']['order']['order_number'] ?? null;
$orderAmountVes = $res['body']['data']['order']['amount_ves'] ?? 1400.00;

// 32. Vendedora Concilia Pago Móvil (Consulta Plus Sandbox) -> Genera Comisión
if ($sellerOrderNumber) {
    $res = httpCall('POST', '/payments/process', [
        'order_number'         => $sellerOrderNumber,
        'payment_method'       => 'pago_movil',
        'reference'            => 'PM' . random_int(10000000, 99999999),
        'identificadorPersona' => str_replace(['-', ' '], '', $sellerClientCi),
        'date'                 => date('Y-m-d'),
        'amount'               => (float)$orderAmountVes,
    ], $sellerToken);
    logStep('SELLER', 'Pago móvil de contrato por vendedora (genera comisión)', '/payments/process', $res, 200);
}

// 33. Vendedora Consulta sus Comisiones
$res = httpCall('GET', '/seller/commissions', [], $sellerToken);
logStep('SELLER', 'Consultar comisiones ganadas', '/seller/commissions', $res, 200);
$firstCommissionId = $res['body']['data']['commissions'][0]['id'] ?? null;

// 34. Vendedora Solicita Retiro de Comisiones
$res = httpCall('POST', '/seller/commissions/withdraw', [], $sellerToken);
logStep('SELLER', 'Solicitar liquidación/retiro de comisiones', '/seller/commissions/withdraw', $res, 200);

// 35. Administrador Liquida y Paga la Comisión
if ($firstCommissionId) {
    $res = httpCall('POST', "/admin/commissions/{$firstCommissionId}/pay", [
        'payment_reference' => 'REF-BANCO-' . date('YmdHis'),
    ], $adminToken);
    logStep('ADMIN', 'Admin liquida comisión a la vendedora', "/admin/commissions/{$firstCommissionId}/pay", $res, 200);
}

// 36. Logout Vendedora
$res = httpCall('POST', '/logout', [
    'refresh_token' => $sellerRefreshToken,
], $sellerToken);
logStep('SELLER', 'Cierre de sesión de Vendedora', '/logout', $res, 200);

echo "\n";

// ======================================================================
// FASE 4: CHOFER / GRUERO (DRIVER)
// ======================================================================
echo "--- FASE 4: CHOFER / GRUERO (Flota, Turnos, Radar, Aceptar SOS, Telemetría) ---\n";

$driverEmail = "chofer_{$rand}@testvial.com";
$driverPass = "DriverPass123!";
$driverPhone = "0412" . random_int(1000000, 9999999);
$driverCi = "V-" . random_int(20000000, 35000000);

// 37. Registro Chofer
$res = httpCall('POST', '/register', [
    'email'               => $driverEmail,
    'password'            => $driverPass,
    'phone'               => $driverPhone,
    'role'                => 'driver',
    'terms_accepted'      => true,
    'identification_card' => $driverCi,
    'names'               => 'Jose Manuel',
    'surnames'            => 'Gruero Rivas',
    'birth_date'          => '1987-11-25',
]);
logStep('DRIVER', 'Registro de Chofer', '/register', $res, 201);

// Asegurar rol driver en DB
$driverRow = $pdo->query("SELECT id FROM users WHERE email = '{$driverEmail}'")->fetch();
$driverId = (int)$driverRow['id'];
$driverRoleRow = $pdo->query("SELECT id FROM roles WHERE name = 'driver'")->fetch();
$driverRoleId = (int)$driverRoleRow['id'];
$pdo->exec("
    INSERT INTO role_user (role_id, user_id) 
    SELECT {$driverRoleId}, {$driverId}
    WHERE NOT EXISTS (SELECT 1 FROM role_user WHERE role_id = {$driverRoleId} AND user_id = {$driverId})
");

// 38. Login Chofer
$res = httpCall('POST', '/login', [
    'email'    => $driverEmail,
    'password' => $driverPass,
]);
logStep('DRIVER', 'Inicio de sesión de Chofer', '/login', $res, 200);
$driverToken = $res['body']['data']['token'] ?? null;
$driverRefreshToken = $res['body']['data']['refresh_token'] ?? null;

// 39. Refresh Token Chofer
$res = httpCall('POST', '/refresh-token', [
    'refresh_token' => $driverRefreshToken,
]);
logStep('DRIVER', 'Refrescar token de Chofer', '/refresh-token', $res, 200);
if (!empty($res['body']['data']['token'])) {
    $driverToken = $res['body']['data']['token'];
    $driverRefreshToken = $res['body']['data']['refresh_token'];
}

// 40. Ingresar Grúa (modelo oficial de grúa Ford F-8000 CH/LARGO con tipo CRANE)
$cranePlate = "GR" . random_int(100, 999) . "VZ";
$craneNiv = "1HGCR2F83HC" . random_int(100000, 999999);
$res = httpCall('POST', '/driver/cranes', [
    'plate_number' => $cranePlate,
    'niv'          => $craneNiv,
    'year'         => 2018,
    'color'        => 'Amarillo',
    'brand'        => 'Ford',
    'model'        => 'F-8000 CH/LARGO',
], $driverToken);
logStep('DRIVER', 'Registrar unidad de grúa', '/driver/cranes', $res, 201);
$craneId = $res['body']['data']['crane']['uuid'] ?? ($res['body']['data']['crane']['id'] ?? null);

// Activamos la grúa y la marcamos como verificada en DB para que sea operable
if ($craneId) {
    $pdo->exec("UPDATE vehicles SET status = 'ACTIVE', is_verified = 1 WHERE uuid = '{$craneId}'");
}

// 41. Listar Grúas
$res = httpCall('GET', '/driver/cranes', [], $driverToken);
logStep('DRIVER', 'Listar grúas registradas del operador', '/driver/cranes', $res, 200);

// 42. Activar Grúa
if ($craneId) {
    $res = httpCall('POST', "/driver/cranes/{$craneId}/activate", [
        'crane_id' => $craneId,
    ], $driverToken);
    logStep('DRIVER', 'Activar grúa de guardia', "/driver/cranes/{$craneId}/activate", $res, 200);
}

// 43. Iniciar Turno (o verificar si ya está activo)
if ($craneId) {
    $res = httpCall('POST', '/driver/shift/start', [
        'crane_id'  => $craneId,
        'latitude'  => 10.496000,
        'longitude' => -66.852000,
    ], $driverToken);
    logStep('DRIVER', 'Iniciar turno de guardia', '/driver/shift/start', $res, in_array($res['status'], [200, 409]) ? $res['status'] : 200);
}

// 44. Consultar Turno Actual
$res = httpCall('GET', '/driver/shift/current', [], $driverToken);
logStep('DRIVER', 'Consultar turno activo de chofer', '/driver/shift/current', $res, 200);

// 45. Actualizar Ubicación GPS del Chofer
$res = httpCall('POST', '/driver/location', [
    'latitude'  => 10.495500,
    'longitude' => -66.851000,
], $driverToken);
logStep('DRIVER', 'Actualizar ubicación GPS del chofer', '/driver/location', $res, 200);

// 46. Consultar Radar de Solicitudes SOS
$res = httpCall('GET', '/sos/radar', [], $driverToken, [], ['lat' => 10.495000, 'lng' => -66.850000]);
logStep('DRIVER', 'Consultar radar de solicitudes SOS', '/sos/radar', $res, 200);

// 47. Chofer Acepta el Servicio SOS del Cliente (creado en Paso 13)
if ($sosUuid) {
    $res = httpCall('POST', "/sos/{$sosUuid}/accept", [], $driverToken);
    logStep('DRIVER', 'Aceptar servicio de auxilio vial', "/sos/{$sosUuid}/accept", $res, 200);

    // 48. Autenticación de Canal Privado Pusher (WebSockets)
    $res = httpCall('POST', '/broadcasting/auth', [
        'channel_name' => "private-tow-{$sosUuid}",
        'socket_id'    => '12345.67890',
    ], $driverToken);
    logStep('DRIVER', 'Handshake canal privado Pusher WebSockets', '/broadcasting/auth', $res, 200);

    // 49. Telemetría GPS en tiempo real
    $res = httpCall('POST', "/sos/{$sosUuid}/telemetry", [
        'lat'       => 10.495200,
        'lng'       => -66.850500,
        'speed_kmh' => 45.2,
    ], $driverToken);
    logStep('DRIVER', 'Enviar pulso de telemetría GPS', "/sos/{$sosUuid}/telemetry", $res, 200);

    // 50. Chofer Notifica Llegada al Sitio
    $res = httpCall('POST', "/sos/{$sosUuid}/arrive", [], $driverToken);
    logStep('DRIVER', 'Notificar llegada al sitio de avería', "/sos/{$sosUuid}/arrive", $res, 200);

    // 51. Chofer Completa el Servicio
    $res = httpCall('POST', "/sos/{$sosUuid}/complete", [], $driverToken);
    logStep('DRIVER', 'Completar remolque de grúa', "/sos/{$sosUuid}/complete", $res, 200);

    // 52. Cliente Califica el Servicio
    $res = httpCall('POST', "/sos/{$sosUuid}/rate", [
        'rating'  => 5,
        'comment' => 'Excelente servicio de grúa, llegó súper rápido y fue muy atento.',
    ], $clientToken);
    logStep('CLIENT', 'Calificar servicio culminado (5 estrellas)', "/sos/{$sosUuid}/rate", $res, 200);
}

// 53. Finalizar Turno de Chofer
$res = httpCall('POST', '/driver/shift/end', [], $driverToken);
logStep('DRIVER', 'Finalizar turno de chofer', '/driver/shift/end', $res, 200);

// 54. Logout Chofer
$res = httpCall('POST', '/logout', [
    'refresh_token' => $driverRefreshToken,
], $driverToken);
logStep('DRIVER', 'Cierre de sesión de Chofer', '/logout', $res, 200);

echo "\n======================================================================\n";
echo "📊 RESUMEN FINAL DE PRUEBAS EN CALIENTE\n";
echo "======================================================================\n";

$total = count($report);
$passed = count(array_filter($report, fn($r) => $r['ok']));
$failed = $total - $passed;

echo "Total endpoints evaluados: {$total}\n";
echo "Exitosos: {$passed}\n";
echo "Fallidos: {$failed}\n";

if ($failed > 0) {
    echo "\n⚠️ Detalle de pruebas con discrepancias:\n";
    foreach ($report as $r) {
        if (!$r['ok']) {
            echo " - [{$r['module']}] {$r['test']} ({$r['endpoint']}): HTTP {$r['status']} vs Esperado {$r['expected_status']}\n";
            echo "   Respuesta: " . json_encode($r['response'], JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
} else {
    echo "\n🎉 TODOS LOS ENDPOINTS FUNCIONARON CON ÉXITO EN CALIENTE (100% OK).\n";
}
