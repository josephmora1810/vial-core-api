<?php

namespace App\Application\UseCases;

use App\Application\DTOs\UserResponseDto;
use App\Core\JwtService;
use App\Domain\Entities\PersonalAccessToken;
use App\Domain\Repositories\PersonalAccessTokenRepositoryInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use DateTimeImmutable;
use Exception;

class RefreshToken
{
    public function __construct(
        private PersonalAccessTokenRepositoryInterface $tokenRepository,
        private UserRepositoryInterface $userRepository,
        private JwtService $jwtService
    ) {}

    public function execute(string $rawRefreshToken): array
    {
        if (empty(trim($rawRefreshToken))) {
            throw new Exception("El refresh token es requerido.", 400);
        }

        $tokenHash = hash('sha256', trim($rawRefreshToken));
        $tokenEntity = $this->tokenRepository->findByTokenHash($tokenHash);

        // 1. Validar existencia, vigencia y que no esté revocado
        if (!$tokenEntity || !$tokenEntity->isValid()) {
            throw new Exception("El refresh token es inválido, ha expirado o fue revocado.", 401);
        }

        // 2. Resolver al usuario por su UUID público
        $userUuid = $this->tokenRepository->findUserUuidByTokenHash($tokenHash);
        if (!$userUuid) {
            throw new Exception("Usuario asociado al token no encontrado.", 401);
        }

        $user = $this->userRepository->findByUuid($userUuid);
        if (!$user || !$user->isActive()) {
            throw new Exception("La cuenta de usuario está inactiva o suspendida.", 403);
        }

        // 3. Rotación de Token (Invalida el token anterior para mitigar secuestro de sesión)
        $tokenEntity->revoke();
        $this->tokenRepository->save($tokenEntity);

        $newPlainRefreshToken = bin2hex(random_bytes(32));
        $newExpiresAt = (new DateTimeImmutable())->modify('+30 days');

        $newTokenEntity = PersonalAccessToken::create(
            userId: (int)$user->getId(),
            plainToken: $newPlainRefreshToken,
            expiresAt: $newExpiresAt
        );
        $this->tokenRepository->save($newTokenEntity);

        // 4. Emitir nuevo Access Token JWT
        $newJwt = $this->jwtService->generateToken([
            'sub'   => $user->getUuid()->getValue(),
            'email' => $user->getEmail()->getValue(),
            'role'  => $user->getRole()?->getName() ?? 'client',
            'roles' => $user->getRoleNames(),
            'name'  => $user->getFullName(),
        ]);

        return [
            'user'          => UserResponseDto::fromEntity($user)->toArray(),
            'token'         => $newJwt,
            'refresh_token' => $newPlainRefreshToken
        ];
    }
}
