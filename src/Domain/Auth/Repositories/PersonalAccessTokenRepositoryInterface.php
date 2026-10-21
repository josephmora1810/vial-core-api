<?php

namespace App\Domain\Auth\Repositories;

use App\Domain\Auth\Entities\PersonalAccessToken;
use App\Domain\Shared\ValueObjects\Uuid;

/**
 * Contrato de repositorio para el almacenamiento de Refresh Tokens.
 */
interface PersonalAccessTokenRepositoryInterface
{
    public function save(PersonalAccessToken $token): void;
    public function findByTokenHash(string $tokenHash): ?PersonalAccessToken;
    public function findUserUuidByTokenHash(string $tokenHash): ?Uuid;
    public function revokeByTokenHash(string $tokenHash): void;
    public function revokeAllForUserId(int $userId): void;
}
