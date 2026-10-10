<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\User;
use App\Domain\ValueObjects\Email;

interface UserRepositoryInterface
{
    public function save(/**User*/ $user): void;
    public function findByEmail(Email $email) /**:?User*/;
}