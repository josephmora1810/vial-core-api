<?php

/**
 * Ejecutor Maestro de Cronjobs del Servidor (UbikGo Core API)
 *
 * Ejecuta en secuencia las tareas programadas exclusivas del servidor:
 *   1. sync_bank_token.php     -> Renueva el token OAuth2 de Bancaribe en MariaDB y Redis
 *   2. sync_exchange_rates.php -> Sincroniza tasas oficiales BCV (USD/EUR) en MariaDB y Redis
 *   3. expire_contracts.php    -> Pasa a EXPIRED los contratos cuya fecha/hora expires_at ya venció
 *   4. expire_sos_timeouts.php -> Cancela solicitudes SOS en PENDING tras 10 min y reembolsa a wallet
 *   5. process_s3_queue.php    -> Procesa la cola de subidas asíncronas de documentos a AWS S3
 *
 * Uso:
 *   php scripts/run_cronjobs.php
 */

$scripts = [
    __DIR__ . '/sync_bank_token.php',
    __DIR__ . '/sync_exchange_rates.php',
    __DIR__ . '/expire_contracts.php',
    __DIR__ . '/expire_sos_timeouts.php',
    __DIR__ . '/process_s3_queue.php',
];

$exitCode = 0;
foreach ($scripts as $script) {
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script);
    passthru($cmd, $code);
    if ($code !== 0) {
        $exitCode = $code;
    }
}

exit($exitCode);
