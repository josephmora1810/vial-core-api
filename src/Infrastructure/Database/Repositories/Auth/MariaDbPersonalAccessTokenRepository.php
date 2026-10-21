<?php

namespace App\Infrastructure\Database\Repositories\Auth;

use App\Domain\Auth\Entities\PersonalAccessToken;
use App\Domain\Auth\Repositories\PersonalAccessTokenRepositoryInterface;
use App\Domain\Shared\ValueObjects\Uuid;
use DateTimeImmutable;
use PDO;

class MariaDbPersonalAccessTokenRepository implements PersonalAccessTokenRepositoryInterface
{
    public function __construct(private PDO $connection) {}

    public function save(PersonalAccessToken $token): void
    {
        if ($token->getId() === null) {
            $this->insert($token);
        } else {
            $this->update($token);
        }
    }

    private function insert(PersonalAccessToken $token): void
    {
        $stmt = $this->connection->prepare('
            INSERT INTO personal_access_tokens (user_id, token, expires_at, is_revoked, created_at)
            VALUES (:user_id, :token, :expires_at, :is_revoked, :created_at)
        ');

        $stmt->execute([
            ':user_id'    => $token->getUserId(),
            ':token'      => $token->getTokenHash(),
            ':expires_at' => $token->getExpiresAt()->format('Y-m-d H:i:s'),
            ':is_revoked' => $token->isRevoked() ? 1 : 0,
            ':created_at' => $token->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);

        $id = (int)$this->connection->lastInsertId();
        $token->setId($id);
    }

    private function update(PersonalAccessToken $token): void
    {
        $stmt = $this->connection->prepare('
            UPDATE personal_access_tokens SET
                is_revoked = :is_revoked,
                expires_at = :expires_at
            WHERE id = :id
        ');

        $stmt->execute([
            ':id'         => $token->getId(),
            ':is_revoked' => $token->isRevoked() ? 1 : 0,
            ':expires_at' => $token->getExpiresAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByTokenHash(string $tokenHash): ?PersonalAccessToken
    {
        $stmt = $this->connection->prepare('
            SELECT id, user_id, token, expires_at, is_revoked, created_at
            FROM personal_access_tokens
            WHERE token = :token
            LIMIT 1
        ');

        $stmt->execute([':token' => $tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new PersonalAccessToken(
            id: (int)$row['id'],
            userId: (int)$row['user_id'],
            tokenHash: $row['token'],
            expiresAt: new DateTimeImmutable($row['expires_at']),
            isRevoked: (bool)$row['is_revoked'],
            createdAt: new DateTimeImmutable($row['created_at'])
        );
    }

    public function findUserUuidByTokenHash(string $tokenHash): ?Uuid
    {
        $stmt = $this->connection->prepare('
            SELECT u.uuid
            FROM personal_access_tokens pat
            INNER JOIN users u ON pat.user_id = u.id
            WHERE pat.token = :token AND pat.is_revoked = 0 AND pat.expires_at > NOW()
            LIMIT 1
        ');

        $stmt->execute([':token' => $tokenHash]);
        $uuid = $stmt->fetchColumn();

        return $uuid ? new Uuid($uuid) : null;
    }

    public function revokeByTokenHash(string $tokenHash): void
    {
        $stmt = $this->connection->prepare('
            UPDATE personal_access_tokens
            SET is_revoked = 1
            WHERE token = :token
        ');

        $stmt->execute([':token' => $tokenHash]);
    }

    public function revokeAllForUserId(int $userId): void
    {
        $stmt = $this->connection->prepare('
            UPDATE personal_access_tokens
            SET is_revoked = 1
            WHERE user_id = :user_id
        ');

        $stmt->execute([':user_id' => $userId]);
    }
}
