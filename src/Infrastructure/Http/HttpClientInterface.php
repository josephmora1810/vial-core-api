<?php

namespace App\Infrastructure\Http;

/**
 * Contrato del cliente HTTP.
 * Gracias a esta interfaz, en los tests podemos inyectar un FakeHttpClient
 * que devuelva respuestas fijas, sin levantar el OCR real.
 */
interface HttpClientInterface
{
    /** @param array<string, string> $headers */
    public function get(string $url, array $query = [], array $headers = []): HttpResponse;

    /** @param array<string, string> $headers */
    public function postJson(string $url, array $data, array $headers = []): HttpResponse;

    /**
     * Envía multipart/form-data (formularios con archivos).
     *
     * @param array<string, string> $fields Campos de texto: ['document_type' => 'identity_card']
     * @param array<string, string> $files  Archivos: ['document' => '/ruta/temporal/foto.jpg']
     * @param array<string, string> $headers
     */
    public function postMultipart(string $url, array $fields, array $files, array $headers = []): HttpResponse;
}