<?php

namespace App\Domain\Shared\Services;

interface QueueServiceInterface
{
    /**
     * Encola un trabajo en segundo plano (Redis List / Queue).
     *
     * @param string $queue Nombre de la cola (ej: queue:s3_document_uploads)
     * @param array<string, mixed> $payload Datos del trabajo serializables a JSON
     */
    public function push(string $queue, array $payload): void;

    /**
     * Extrae el siguiente trabajo de la cola (LPOP) o retorna null si está vacía.
     *
     * @param string $queue
     * @return array<string, mixed>|null
     */
    public function pop(string $queue): ?array;

    /**
     * Retorna la cantidad de trabajos pendientes en la cola.
     *
     * @param string $queue
     * @return int
     */
    public function size(string $queue): int;
}
