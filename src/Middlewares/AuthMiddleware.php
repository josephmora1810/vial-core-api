<?php

namespace App\Middlewares;

use App\Core\{
    JwtService,
    MiddlewareInterface,
    Request
};
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Users\Repositories\UserRepositoryInterface;
use Closure;
use Exception;
use InvalidArgumentException;

/**
 * Centinela de Autenticación.
 * Lee el token Bearer, valida su firma con JwtService y busca al usuario en MariaDB.
 */
class AuthMiddleware implements MiddlewareInterface
{
    /**
     * @param array<string> $publicRoutes Rutas opcionales excluidas de autenticación si se usa como middleware global.
     *                                   Ejemplo: ['GET:/api/v1/ping', 'POST:/api/v1/login'] o ['/api/v1/ping']
     */
    public function __construct(
        private JwtService $jwtService,
        private UserRepositoryInterface $userRepository,
        private array $publicRoutes = []
    ) {}

    public function handle(Request $request, Closure $next)
    {
        // 1. Si la ruta actual está en la lista de rutas públicas, dejamos pasar la petición
        if ($this->isPublicRoute($request)) {
            return $next($request);
        }

        // 2. Extraer el token Bearer (usando el helper de Request con soporte para Apache)
        $token = $request->bearerToken();

        if (empty($token)) {
            throw new Exception("Token de autenticación no proporcionado.", 401);
        }

        // 3. Validar la firma y vigencia del token JWT
        $payload = $this->jwtService->validateToken($token);

        if (empty($payload['sub'])) {
            throw new Exception("El token no contiene un identificador de usuario válido.", 401);
        }

        // 4. Convertir el UUID del payload al Value Object Uuid
        try {
            $uuid = new Uuid($payload['sub']);
        } catch (InvalidArgumentException) {
            throw new Exception("Identificador de usuario inválido en el token.", 401);
        }

        // 5. Buscar al usuario en la base de datos mediante su Value Object Uuid
        $user = $this->userRepository->findByUuid($uuid);

        if (!$user || !$user->isActive()) {
            throw new Exception("Usuario no encontrado o cuenta desactivada.", 401);
        }

        // 6. Inyectar el usuario autenticado en la petición
        $request->setUser($user);

        return $next($request);
    }

    /**
     * Comprueba si la petición actual coincide con alguna de las rutas públicas configuradas
     */
    private function isPublicRoute(Request $request): bool
    {
        $currentUri = $request->uri();
        $currentMethod = $request->method();
        $methodAndUri = "{$currentMethod}:{$currentUri}";

        foreach ($this->publicRoutes as $publicRoute) {
            // Coincidencia exacta con método (ej: "GET:/api/v1/ping") o solo URI (ej: "/api/v1/ping")
            if ($publicRoute === $methodAndUri || $publicRoute === $currentUri) {
                return true;
            }

            // Soporte de comodines simples (ej: "/api/v1/public/*")
            if (str_ends_with($publicRoute, '*')) {
                $base = rtrim($publicRoute, '*');
                if (str_starts_with($currentUri, $base) || str_starts_with($methodAndUri, $base)) {
                    return true;
                }
            }
        }

        return false;
    }
}
