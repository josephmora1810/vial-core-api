<?php

namespace App\Domain\Users\Repositories;

use App\Domain\Users\Entities\User;
use App\Domain\Users\ValueObjects\Email;
use App\Domain\Users\ValueObjects\NationalId;
use App\Domain\Shared\ValueObjects\Uuid;

interface UserRepositoryInterface
{
    public function save(User $user): void;
    public function findById(int $id): ?User;
    public function findByEmail(Email $email): ?User;
    public function findByUuid(Uuid $uuid): ?User;
    public function findByIdentificationCard(NationalId $nationalId): ?User;
}