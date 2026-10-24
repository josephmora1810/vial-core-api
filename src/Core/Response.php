<?php

declare(strict_types=1);

namespace App\Core;

class Response
{
    public static bool $shouldExit = true;

    /**
     * @param mixed $data
     * @param int $code
     * @param string $status
     * @param string $message
     * @param array<string, mixed>|null $meta
     */
    public static function json(
        mixed $data = [],
        int $code = 200,
        string $status = 'success',
        string $message = '',
        ?array $meta = null
    ): void {
        http_response_code($code);
        if (!headers_sent()) {
            header('Content-type: application/json; charset=utf-8');
        }

        if (is_object($data) && method_exists($data, 'toArray')) {
            $data = $data->toArray();
        }

        $response = [
            'status'  => $status,
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ];

        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (self::$shouldExit) {
            exit;
        }
    }

    /**
     * @param mixed $data
     * @param int $code
     */
    public static function rawJson(mixed $data, int $code = 200): void
    {
        http_response_code($code);
        if (!headers_sent()) {
            header('Content-type: application/json; charset=utf-8');
        }

        if (is_object($data) && method_exists($data, 'toArray')) {
            $data = $data->toArray();
        }

        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (self::$shouldExit) {
            exit;
        }
    }
}
