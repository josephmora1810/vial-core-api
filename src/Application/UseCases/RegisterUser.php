<?php

namespace App\Application\UseCases;

use App\Domain\Entities\User;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\ValueObjects\Email;
use App\Domain\ValueObjects\Password;
use Exception;

class RegisterUser
{
    public function __construct(private UserRepositoryInterface $userRepository)
    {
    }

    public function execute(string $name, string $rawEmail, string $rawPassword): void
    {
        $email = new Email($rawEmail); // Valida si es email correcto
        
        if ($this->userRepository->findByEmail($email) !== null) {
            throw new Exception("El usuario ya existe.");
        }

        $password = Password::fromPlainText($rawPassword); // Valida y hashea

        $user = new User(null, $name, $email, $password, true);

        $this->userRepository->save($user); // Guarda usando PDO sin saber que es PDO
    }
}