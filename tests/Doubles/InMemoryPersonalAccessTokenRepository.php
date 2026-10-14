<?php

namespace Tests\Doubles;

use App\Domain\Entities\PersonalAccessToken;
use App\Domain\Repositories\PersonalAccessTokenRepositoryInterface;
use App\Domain\ValueObjects\Uuid;

class InMemoryPersonalAccessTokenRepository implements PersonalAccessTokenRepositoryInterface
{
    /** @var array<string, PersonalAccessToken> */
    private array $tokensByHash = [];
    /** @var array<string, Uuid> */
    private array $userUuidsByHash = [];
    private ?InMemoryUserRepository $userRepository = null;
    private int $nextId = 1;

    public function __construct(?InMemoryUserRepository $userRepository = null)
    {
        $this->userRepository = $userRepository;
    }

    public function setUserRepository(InMemoryUserRepository $userRepository): void
    {
        $this->userRepository = $userRepository;
    }

    public function save(PersonalAccessToken $token): void
    {
        if ($token->getId() === null) {
            $token->setId($this->nextId++);
        }
        $this->tokensByHash[$token->getTokenHash()] = $token;
    }

    public function bindUserUuid(string $tokenHash, Uuid $uuid): void
    {
        $this->userUuidsByHash[$tokenHash] = $uuid;
    }

    public function findByTokenHash(string $tokenHash): ?PersonalAccessToken
    {
        return $this->tokensByHash[$tokenHash] ?? null;
    }

    public function findUserUuidByTokenHash(string $tokenHash): ?Uuid
    {
        $token = $this->findByTokenHash($tokenHash);
        if (!$token || !$token->isValid()) {
            return null;
        }

        if (isset($this->userUuidsByHash[$tokenHash])) {
            return $this->userUuidsByHash[$tokenHash];
        }

        if ($this->userRepository !== null) {
            $user = $this->userRepository->findByIdInternal($token->getUserId());
            return $user?->getUuid();
        }

        return null;
    }

    public function revokeByTokenHash(string $tokenHash): void
    {
        if (isset($this->tokensByHash[$tokenHash])) {
            $this->tokensByHash[$tokenHash]->revoke();
        }
    }

    public function revokeAllForUserId(int $userId): void
    {
        foreach ($this->tokensByHash as $token) {
            if ($token->getUserId() === $userId) {
                $token->revoke();
            }
        }
    }
}
