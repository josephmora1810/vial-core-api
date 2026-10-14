<?php

namespace App\Application\UseCases;

use App\Domain\Repositories\PersonalAccessTokenRepositoryInterface;
use Exception;

class LogoutUser
{
    public function __construct(
        private PersonalAccessTokenRepositoryInterface $tokenRepository
    ) {}

    public function execute(string $rawRefreshToken): void
    {
        if (empty(trim($rawRefreshToken))) {
            throw new Exception("El refresh token es requerido para cerrar sesión.", 400);
        }

        $tokenHash = hash('sha256', trim($rawRefreshToken));
        $this->tokenRepository->revokeByTokenHash($tokenHash);
    }
}
