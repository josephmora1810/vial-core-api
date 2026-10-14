<?php

namespace Tests\Doubles;

use App\Domain\Entities\User;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\ValueObjects\Email;
use App\Domain\ValueObjects\Uuid;

/**
 * Repositorio en memoria para tests unitarios rápidos y deterministas (DDD).
 * No toca base de datos real.
 */
class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<string, User> */
    private array $usersByEmail = [];
    /** @var array<string, User> */
    private array $usersByUuid = [];
    /** @var array<int, User> */
    private array $usersById = [];
    private int $nextId = 1;

    public function save(User $user): void
    {
        if ($user->getId() === null) {
            $user->setId($this->nextId++);
        }

        $this->usersByEmail[$user->getEmail()->getValue()] = $user;
        $this->usersByUuid[$user->getUuid()->getValue()] = $user;
        $this->usersById[$user->getId()] = $user;
    }

    public function findByEmail(Email $email): ?User
    {
        return $this->usersByEmail[$email->getValue()] ?? null;
    }

    public function findByUuid(Uuid $uuid): ?User
    {
        return $this->usersByUuid[$uuid->getValue()] ?? null;
    }

    public function findByIdInternal(int $id): ?User
    {
        return $this->usersById[$id] ?? null;
    }
}
