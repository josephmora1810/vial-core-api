<?php

namespace App\Application\UseCases;

use App\Core\JwtService;
use App\Domain\Entities\User;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\ValueObjects\Email;
use App\Domain\ValueObjects\Password;
use Exception;

class RegisterUser
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private JwtService $jwtService
    ){}

    public function execute(string $name, string $rawEmail, string $rawPassword): void
    {
        $email = new Email($rawEmail); // Valida si es email correcto
        
        if ($this->userRepository->findByEmail($email) !== null) {
            throw new Exception("El usuario ya existe.");
        }

        $password = Password::fromPlainText($rawPassword); // Valida y hashea

        // hay que crear un nuevo usuario

        //$this->userRepository->save(/**$user*/); // Guarda usando PDO sin saber que es PDO
    }
}