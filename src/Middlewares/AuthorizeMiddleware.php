<?php

namespace App\Middlewares;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Domain\Users\Entities\User;
use Closure;
use Exception;

/**
 * Middleware de Autorización RBAC.
 * Valida roles (role:client) o permisos (permission:request_sos).
 * Utiliza el trait HasRolesAndPermissions de la entidad User.
 */
class AuthorizeMiddleware implements MiddlewareInterface
{
    /**
     * @param string $type  'role' o 'permission'
     * @param string $value Nombre del rol o permiso a evaluar
     */
    public function __construct(
        private string $type,
        private string $value
    ) {}

    public static function role(string $role): self
    {
        return new self('role', $role);
    }

    public static function permission(string $permission): self
    {
        return new self('permission', $permission);
    }

    public function handle(Request $request, Closure $next)
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            throw new Exception("Debes iniciar sesión para realizar esta acción.", 401);
        }

        if ($this->type === 'role') {
            // Verifica si el usuario posee el rol específico
            if (!$user->hasRole($this->value)) {
                throw new Exception("No tienes el rol requerido ({$this->value}) para acceder a este recurso.", 403);
            }
        } elseif ($this->type === 'permission') {
            // Verifica permiso directo o heredado a través de sus roles
            if (!$user->can($this->value)) {
                throw new Exception("No tienes los permisos necesarios para realizar esta acción.", 403);
            }
        }

        return $next($request);
    }
}
