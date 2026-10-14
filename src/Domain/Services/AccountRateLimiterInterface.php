<?php

namespace App\Domain\Services;

/**
 * Puerto de Dominio para el control y mitigación de ataques de fuerza bruta (FR-05).
 * Permite bloquear temporalmente el acceso a cuentas con múltiples intentos fallidos.
 */
interface AccountRateLimiterInterface
{
    /**
     * Verifica si una cuenta o identidad se encuentra bloqueada por exceso de intentos fallidos.
     *
     * @param string $identity Identificador único (ej. email)
     * @return bool
     */
    public function isLocked(string $identity): bool;

    /**
     * Registra un intento fallido de autenticación e incrementa el contador.
     *
     * @param string $identity Identificador único (ej. email)
     * @param int $lockoutSeconds Tiempo de bloqueo o ventana de expiración en segundos (por defecto 900s = 15min)
     */
    public function recordFailedAttempt(string $identity, int $lockoutSeconds = 900): void;

    /**
     * Restablece el contador de intentos tras una autenticación exitosa.
     *
     * @param string $identity Identificador único (ej. email)
     */
    public function resetAttempts(string $identity): void;
}
