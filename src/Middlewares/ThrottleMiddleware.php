<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\{
    MiddlewareInterface,
    Request,
    Response
};
use App\Infrastructure\Database\Redis;
use Closure;
use Exception;

class ThrottleMiddleware implements MiddlewareInterface
{
    private const string KEY_PREFIX = "throttle:";
    private const string KEY_LOGIN_PREFIX = "login";
    private const string KEY_API_PREFIX = "api";

    public function __construct(private Redis $redis) {}

    public function handle(Request $request, Closure $next)
    {
        /**
         * Por ahora, usamos solo la ip
         * cuando tengamos implementado el login,
         * usaremos el id del usuario junto a la ip
         */
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $uri = $request->uri();

        $isLogin = strpos($uri, '/login') !== false;

        $key = self::KEY_PREFIX . ($isLogin ? self::KEY_LOGIN_PREFIX : self::KEY_API_PREFIX) . ":" . $ip;

        $limit = $isLogin ? 5 : 60;
        $decay = 60;

        try {
            $currentAttempts = $this->redis->execute("GET", $key);

            if ($currentAttempts === null || $currentAttempts === false) {
                $this->redis->execute("SET", $key, "1");
                $this->redis->execute("EXPIRE", $key, (string)$decay);
                $currentAttempts = 1;
            } else {
                $currentAttempts = (int) $currentAttempts;

                if ($currentAttempts >= $limit) {
                    $ttl = (int) $this->redis->execute("TTL", $key);
                    $ttl = ($ttl > 0) ? $ttl : $decay;

                    header("Retry-After: $ttl");
                    header("X-RateLimit-Limit: $limit");
                    header("X-RateLimit-Remaining: 0");

                    Response::json(
                        [],
                        429,
                        'fail',
                        "Demasiados intentos. Por favor, espere {$ttl} segundos antes de volver a intentarlo."
                    );
                    return;
                }

                $this->redis->execute("INCR", $key);
                $currentAttempts++;
            }

            $remaining = max(0, $limit - $currentAttempts);
            header("X-RateLimit-Limit: $limit");
            header("X-RateLimit-Remaining: $remaining");
        } catch (Exception $e) {
            // Si Redis falla, dejamos pasar la petición para alta disponibilidad (Fail-open)
            error_log("Fallo de conexión a Redis en ThrottleMiddleware: " . $e->getMessage());
        }

        return $next($request);
    }
}
