<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\User;
use App\Domain\ValueObjects\Email;
use App\Domain\ValueObjects\Uuid;

interface UserRepositoryInterface
{
    public function save(User $user): void;
    public function findByEmail(Email $email): ?User;
    public function findByUuid(Uuid $uuid): ?User;
}