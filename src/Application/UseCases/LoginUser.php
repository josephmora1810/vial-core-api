<?php

namespace App\Application\UseCases;

use App\Core\JwtService;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\ValueObjects\Email;
use Exception;
use InvalidArgumentException;

class LoginUser
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private JwtService $jwtService
    ) {}

    public function execute(string $rawEmail, string $rawPassword): array
    {
        try {
            $email = new Email($rawEmail);
        } catch (InvalidArgumentException $e) {
            throw new Exception("El formato del correo electrónico no es válido.", 400);
        }

        $user = $this->userRepository->findByEmail($email);

        // Verificamos existencia y contraseña con hash bcrypt seguro
        if (!$user || !$user->getPassword()?->verify($rawPassword)) {
            throw new Exception("Credenciales incorrectas.", 401);
        }

        if (!$user->isActive()) {
            throw new Exception("Tu cuenta ha sido suspendida. Contacta a soporte.", 403);
        }

        $roleName = $user->getRole()?->getName() ?? 'client';
        $roles = $user->getRoleNames();

        // Generamos el payload para el JWT
        $token = $this->jwtService->generateToken([
            'sub'   => $user->getUuid()->getValue(),
            'email' => $user->getEmail()->getValue(),
            'role'  => $roleName,
            'roles' => $roles,
            'name'  => $user->getName()
        ]);

        return [
            'user' => [
                'uuid'  => $user->getUuid()->getValue(),
                'name'  => $user->getName(),
                'email' => $user->getEmail()->getValue(),
                'role'  => $roleName,
                'roles' => $roles,
            ],
            'token' => $token
        ];
    }
}
