<?php

namespace App\Controllers;

use App\Application\UseCases\LoginUser;
use App\Application\UseCases\RegisterUser;
use App\Core\Request;
use App\Core\Response;
use Exception;

class AuthController
{
    public function __construct(
        private LoginUser $loginUseCase,
        private RegisterUser $registerUseCase,
    ) {}

    public function login(Request $request): void
    {
        $body = $request->body();
        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';
        if (empty($email) || empty($password)) {
            Response::json([], 400, 'fail', 'El correo y la contraseña son requeridos.');
            return;
        }
        try {
            $result = $this->loginUseCase->execute($email, $password);
            Response::json($result, 200, 'success', 'Inicio de sesión exitoso.');
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 500 ? $e->getCode() : 401;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    public function me(Request $request): void
    {
        $user = $request->user();

        if (!$user) {
            Response::json([], 401, 'fail', 'Usuario no autenticado.');
            return;
        }

        Response::json([
            'uuid'  => $user->getUuid()->getValue(),
            'name'  => $user->getName(),
            'email' => $user->getEmail()->getValue(),
            'role'  => $user->getRole()?->getName() ?? 'client',
            'roles' => $user->getRoleNames(),
        ], 200, 'success', 'Usuario autenticado.');
    }
}
