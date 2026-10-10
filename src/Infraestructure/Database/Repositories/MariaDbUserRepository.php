<?php

namespace App\Infrastructure\Database;

use App\Domain\Entities\User;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\ValueObjects\Email;
use PDO;

class MariaDbUserRepository implements UserRepositoryInterface
{
    public function __construct(private PDO $connection)
    {
    }

    public function save(User $user): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO users (name, email, password, is_active) VALUES (:name, :email, :password, :is_active)'
        );

        $stmt->execute([
            ':name' => $user->getName(),
            ':email' => $user->getEmail()->getValue(),
            ':password' => $user->getPassword()->getValue(),
            ':is_active' => $user->isActive() ? 1 : 0
        ]);
        
        // Faltaría lógica para setear el ID generado en la entidad, pero esto es el núcleo.
    }

    public function findByEmail(Email $email): ?User
    {
        // ... Logica de SELECT ...
        return null; // O la Entidad User hidratada con fromHash()
    }
}