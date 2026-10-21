<?php

namespace App\Infrastructure\Database\Repositories\Payments;

use App\Domain\Payments\Repositories\BankTokenRepositoryInterface;
use DateTimeImmutable;
use PDO;

class MariaDbBankTokenRepository implements BankTokenRepositoryInterface
{
    public function __construct(private PDO $db) {}

    public function findByGateway(string $gateway): ?array
    {
        $stmt = $this->db->prepare("
            SELECT gateway, access_token, token_type, expires_at
            FROM bank_tokens
            WHERE gateway = :gateway
            LIMIT 1
        ");
        $stmt->execute(['gateway' => $gateway]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'gateway'      => (string)$row['gateway'],
            'access_token' => (string)$row['access_token'],
            'token_type'   => (string)$row['token_type'],
            'expires_at'   => !empty($row['expires_at']) ? new DateTimeImmutable($row['expires_at']) : null,
        ];
    }

    public function upsert(string $gateway, string $accessToken, string $tokenType, DateTimeImmutable $expiresAt): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO bank_tokens (gateway, access_token, token_type, expires_at)
            VALUES (:gateway, :access_token, :token_type, :expires_at)
            ON DUPLICATE KEY UPDATE
                access_token = VALUES(access_token),
                token_type = VALUES(token_type),
                expires_at = VALUES(expires_at)
        ");

        $stmt->execute([
            'gateway'      => $gateway,
            'access_token' => $accessToken,
            'token_type'   => $tokenType ?: 'Bearer',
            'expires_at'   => $expiresAt->format('Y-m-d H:i:s'),
        ]);
    }
}
