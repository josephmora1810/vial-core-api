<?php

namespace App\Middlewares;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Database\Redis;
use Closure;
use Exception;

class ThrottleMiddleware implements MiddlewareInterface {

    private const string KEY_PREFIX = "throttle:";
    private const string KEY_LOGIN_PREFIX = "login";
    private const string KEY_API_PREFIX = "api";
    

    private Redis $redis;
    public function __construct(Redis $redis) {
        $this->redis = $redis;
    }


    public function handle(Request $request, Closure $next) {
        /**
         * Por ahora, usamos solo la ip
         * cuando tengamos implementado el login,
         * usaremos el id del usuario junto a la ip
         */

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $uri = $request->uri();

        $isLogin = strpos($uri, '/login') !== false;
        $limit = $isLogin ? 5 : 60;
        $windowSecond = 60;

        $key = self::KEY_PREFIX . ($isLogin ? self::KEY_LOGIN_PREFIX : self::KEY_API_PREFIX) . ":{$ip}";

        //timestampt en microsegundos para evitar empates

        $nowMs = (int)(microtime(true) * 1000);
        $windowMs = $windowSecond * 1000;

        $luaScript = <<<LUA
            -- Capturamos las variables de manda php, en este caso traería algo como throttle:api:[IP]
            local key = KEYS[1] 
            -- tomamos el tiempo actual en milisegundos
            local now = tonumber(ARGV[1])
            --ventana en milisegundos
            local window_size = tonumber(ARGV[2])
            -- límite de peticiones
            local limit = tonumber(ARGV[3])
            -- Calculamos el inicio de la ventana
            local window_start = now - window_size

            -- Eliminar las peticiones que quedaron fuera de la ventana
            redis.call('ZREMRANGEBYSCORE', key, '-inf', window_start)

            -- Contar cuantas peticiones válidas quedan
            local current_requests = tonumber(redis.call('ZCARD', key))

            -- Si no ha llegado al límite, registramos esta petición
            if current_requests < limit then
                -- Usamos 'now' como Score y también como Value para que sean únicos
                redis.call('ZADD', key, now, now)
                
                -- Renovar la expiración de la llave para no dejar basura en Redis
                redis.call('PEXPIRE', key, window_size)
                
                return { 1, limit - current_requests - 1 }
            else
                -- Límite excedido
                return { 0, 0 }
            end
        LUA;

        // Ejecutamos el script LUA
        // Comando: EVAL script numkeys key arg1 arg2 arg3
        $result = $this->redis->execute('EVAL', $luaScript, 1, $key, $nowMs, $windowMs, $limit);

        $allowed = (bool) $result[0];
        $remaining = (int) $result[1];

        if (!$allowed) {
            throw new Exception("Too many requests. Please try again later.", 429);
        }

        // Si quieres, puedes inyectar las cabeceras de Rate Limit en el Response
        // Pero para eso necesitarías acceso al response, o puedes lanzar una excepción controlada.

        return $next($request);
    }
}