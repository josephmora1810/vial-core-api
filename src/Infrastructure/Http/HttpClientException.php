<?php

namespace App\Infrastructure\Http;

use RuntimeException;

/**
 * Se lanza SOLO cuando no hubo respuesta HTTP:
 * timeout, DNS que no resuelve, conexión rechazada, error de SSL...
 *
 * Un 4xx/5xx NO es esta excepción: eso sí es una respuesta y se devuelve como HttpResponse.
 */
class HttpClientException extends RuntimeException
{
}