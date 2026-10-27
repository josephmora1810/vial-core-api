<?php

namespace App\Infrastructure\Http;

use JsonException;

/**
 * Respuesta HTTP inmutable (readonly): una vez creada, nadie la modifica.
 */
readonly class HttpResponse
{
    /**
     * @param int $status Código HTTP (200, 422, 500...)
     * @param array<string, string> $headers Cabeceras con el nombre en minúsculas
     * @param string $body Cuerpo crudo tal cual llegó
     */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body
    ) {}

    /** 2xx = éxito */
    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * Accesor para el código de estado HTTP
     */
    public function status(): int
    {
        return $this->status;
    }

    /** Las cabeceras HTTP no distinguen mayúsculas, por eso se buscan en minúsculas */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * Accesor para el cuerpo crudo como método
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Decodifica el body como JSON.
     * JSON_THROW_ON_ERROR hace que un JSON roto lance excepción en vez de devolver null en silencio.
     *
     * @throws HttpClientException
     */
    public function json(): array
    {
        if ($this->body === '') {
            return [];
        }

        try {
            $data = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new HttpClientException('La respuesta no es un JSON válido: ' . $e->getMessage(), 0, $e);
        }

        return \is_array($data) ? $data : [];
    }
}
