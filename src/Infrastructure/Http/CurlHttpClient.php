<?php

namespace App\Infrastructure\Http;

use App\Infrastructure\Logging\IntegrationLogger;
use CURLFile;

/**
 * Cliente HTTP en PHP puro usando la extensión cURL.
 */
class CurlHttpClient implements HttpClientInterface
{
    private IntegrationLogger $logger;

    /**
     * @param int $timeout        Segundos máximos para TODA la petición (el OCR con IA puede tardar)
     * @param int $connectTimeout Segundos máximos solo para ESTABLECER la conexión
     */
    public function __construct(
        private int $timeout = 30,
        private int $connectTimeout = 5,
        ?IntegrationLogger $logger = null
    ) {
        $this->logger = $logger ?? new IntegrationLogger();
    }

    public function get(string $url, array $query = [], array $headers = []): HttpResponse
    {
        if (!empty($query)) {
            // Si la URL ya trae "?", agregamos con "&"
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        return $this->send('GET', $url, $headers);
    }

    public function postJson(string $url, array $data, array $headers = []): HttpResponse
    {
        $headers['Content-Type'] = 'application/json';
        $headers['Accept'] ??= 'application/json';

        return $this->send('POST', $url, $headers, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function postForm(string $url, array $data, array $headers = []): HttpResponse
    {
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $headers['Accept'] ??= 'application/json';

        return $this->send('POST', $url, $headers, http_build_query($data));
    }

    public function postMultipart(string $url, array $fields, array $files, array $headers = []): HttpResponse
    {
        $body = $fields;

        foreach ($files as $fieldName => $path) {
            if (!is_file($path) || !is_readable($path)) {
                throw new HttpClientException("El archivo a enviar no existe o no se puede leer: {$fieldName}");
            }

            // Detectamos el MIME REAL leyendo los bytes del archivo (no confiamos en la extensión)
            $mime = mime_content_type($path) ?: 'application/octet-stream';

            // CURLFile hace que cURL lea y envíe el archivo como binario
            $body[$fieldName] = new CURLFile($path, $mime, basename($path));
        }

        // ¡NO pongas Content-Type a mano! Si le pasas un array, cURL genera solo el
        // "multipart/form-data; boundary=----xyz". Si lo pones tú, se pierde el boundary
        // y el servidor no puede separar los campos.
        $headers['Accept'] ??= 'application/json';

        return $this->send('POST', $url, $headers, $body);
    }

    /**
     * Método central: todas las peticiones pasan por aquí.
     *
     * @param string|array|null $body string = cuerpo crudo (JSON) | array = multipart
     */
    private function send(string $method, string $url, array $headers, string|array|null $body = null): HttpResponse
    {
        $startTime = microtime(true);
        $serviceName = IntegrationLogger::detectServiceFromUrl($url);
        $ch = curl_init();

        if ($ch === false) {
            throw new HttpClientException('No se pudo inicializar cURL.');
        }

        $responseHeaders = [];

        // cURL por defecto agrega "Expect: 100-continue" en bodies grandes (como las fotos),
        // lo que le suma ~1 segundo de espera a cada petición. Con "Expect:" vacío lo desactivamos.
        $headers['Expect'] = '';

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,               // Devuelve el body como string en vez de imprimirlo
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_HTTPHEADER     => $this->formatHeaders($headers),

            // --- Seguridad ---
            CURLOPT_FOLLOWLOCATION => false,              // No seguimos redirecciones (evita SSRF)
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS, // Prohíbe file://, ftp://, gopher://...
            CURLOPT_SSL_VERIFYPEER => true,               // Valida el certificado HTTPS (nunca lo pongas en false)
            CURLOPT_SSL_VERIFYHOST => 2,

            // cURL llama a esta función una vez por cada línea de cabecera recibida
            CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (\count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return \strlen($line); // cURL exige que devolvamos los bytes procesados
            },
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($ch);
        $durationMs = (microtime(true) - $startTime) * 1000;

        // curl_errno != 0 significa que NO hubo respuesta (timeout, DNS, conexión rechazada)
        if ($responseBody === false || curl_errno($ch) !== 0) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            $this->logger->log(
                service: $serviceName,
                operation: "{$method} {$url}",
                url: $url,
                statusCode: 0,
                durationMs: $durationMs,
                requestPayload: $body,
                responsePayload: null,
                error: "cURL #{$errno}: {$error}"
            );
            throw new HttpClientException("Error de red hacia {$method} {$url}: {$error} (cURL #{$errno})", $errno);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        $this->logger->log(
            service: $serviceName,
            operation: "{$method} {$url}",
            url: $url,
            statusCode: $status,
            durationMs: $durationMs,
            requestPayload: $body,
            responsePayload: (string) $responseBody
        );

        // Nota: NO llamamos a curl_close(). Desde PHP 8.0 no hace nada y en PHP 8.5 está
        // deprecado. El handle se libera solo cuando $ch sale de alcance.

        return new HttpResponse($status, $responseHeaders, (string) $responseBody);
    }

    /**
     * Convierte ['Authorization' => 'Bearer x'] en ['Authorization: Bearer x'],
     * que es el formato que exige cURL.
     */
    private function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $name => $value) {
            $formatted[] = "{$name}: {$value}";
        }
        return $formatted;
    }
}