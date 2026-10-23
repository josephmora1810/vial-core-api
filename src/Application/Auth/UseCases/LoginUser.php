<?php

namespace App\Application\Auth\UseCases;

use App\Application\Users\DTOs\UserResponseDto;
use App\Core\JwtService;
use App\Domain\Auth\{
    Entities\PersonalAccessToken,
    Repositories\PersonalAccessTokenRepositoryInterface,
    Services\AccountRateLimiterInterface
};
use App\Domain\Users\{
    Repositories\UserRepositoryInterface,
    ValueObjects\Email
};
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;

class LoginUser
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PersonalAccessTokenRepositoryInterface $tokenRepository,
        private JwtService $jwtService,
        private ?AccountRateLimiterInterface $rateLimiter = null
    ) {}

    public function execute(string $rawEmail, string $rawPassword): array
    {
        try {
            $email = new Email($rawEmail);
        } catch (InvalidArgumentException) {
            throw new Exception("El formato del correo electrónico no es válido.", 400);
        }

        // 1. Mitigación de Fuerza Bruta por cuenta (FR-05) desacoplada mediante interfaz de Dominio
        if ($this->rateLimiter?->isLocked($email->getValue())) {
            throw new Exception("Cuenta bloqueada temporalmente tras 5 intentos fallidos. Intente más tarde.", 429);
        }

        $user = $this->userRepository->findByEmail($email);

        // 2. Verificamos credenciales con Bcrypt
        if (!$user || !$user->getPassword()?->verify($rawPassword)) {
            $this->rateLimiter?->recordFailedAttempt($email->getValue());
            throw new Exception("Credenciales incorrectas.", 401);
        }

        // Credenciales correctas: limpiamos contador de intentos fallidos
        $this->rateLimiter?->resetAttempts($email->getValue());

        if (!$user->isActive()) {
            throw new Exception("Tu cuenta ha sido suspendida. Contacta a soporte.", 403);
        }

        $roleName = $user->getRole()?->getName() ?? 'client';
        $roles = $user->getRoleNames();

        // 3. Generamos el JWT Access Token (stateless)
        $jwtToken = $this->jwtService->generateToken([
            'sub'   => $user->getUuid()->getValue(),
            'email' => $user->getEmail()->getValue(),
            'role'  => $roleName,
            'roles' => $roles,
            'name'  => $user->getFullName()
        ]);

        // 4. Emitimos y persistimos el Refresh Token (30 días de vigencia)
        $plainRefreshToken = bin2hex(random_bytes(32));
        $expiresAt = (new DateTimeImmutable())->modify('+30 days');

        $refreshTokenEntity = PersonalAccessToken::create(
            userId: (int)$user->getId(),
            plainToken: $plainRefreshToken,
            expiresAt: $expiresAt
        );
        $this->tokenRepository->save($refreshTokenEntity);

        // 5. Retornamos datos estructurados mediante DTO
        return [
            'user'          => UserResponseDto::fromEntity($user)->toArray(),
            'token'         => $jwtToken,
            'refresh_token' => $plainRefreshToken
        ];
    }
}
