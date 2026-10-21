<?php

/**
 * Cronjob CLI: Vencimiento automático de Contratos de Servicio (Plan de Grúa)
 * Uso en consola / Crontab / Programador de Tareas de Windows:
 *   php scripts/expire_contracts.php
 *
 * Pasa automáticamente todos los contratos en estado ACTIVE cuya fecha `expires_at`
 * ya se haya cumplido (< CURDATE()) al estado EXPIRED (para que al descargarse
 * muestren la marca de agua de CANCELADO/VENCIDO conservando el sello de PAGADO).
 */

require_once __DIR__ . '/../autoload.php';

use App\Application\Contracts\UseCases\ExpireOverdueContracts;
use App\Core\DotEnv;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Repositories\Contracts\MariaDbServiceContractRepository;

(new DotEnv(__DIR__ . '/../.env'))->load();

try {
    $pdo = (new Database())->getConnection();
    $contractRepo = new MariaDbServiceContractRepository($pdo);
    $useCase = new ExpireOverdueContracts($contractRepo);

    $result = $useCase->execute();

    echo sprintf(
        "[%s] Cronjob finalizado exitosamente. Contratos pasados a EXPIRED: %d\n",
        $result['executed_at'],
        $result['expired_count']
    );
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, sprintf("[ERROR CRON CONTRATOS] %s\n", $e->getMessage()));
    exit(1);
}
