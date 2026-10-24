<?php

namespace App\Infrastructure\Database\Repositories\Commissions;

use App\Domain\Commissions\{
    Entities\SellerProfile,
    Repositories\SellerProfileRepositoryInterface
};
use DateTimeImmutable;
use PDO;

class MariaDbSellerProfileRepository implements SellerProfileRepositoryInterface
{
    public function __construct(private PDO $db) {}

    public function findByUserId(int $userId): ?SellerProfile
    {
        $stmt = $this->db->prepare("SELECT * FROM seller_profiles WHERE user_id = :user_id LIMIT 1");
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    public function save(SellerProfile $profile): SellerProfile
    {
        $stmt = $this->db->prepare("
            INSERT INTO seller_profiles (
                user_id, commission_rate, bank_id, account_number,
                account_type, account_holder_name, account_holder_id, phone_pago_movil
            ) VALUES (
                :user_id, :commission_rate, :bank_id, :account_number,
                :account_type, :account_holder_name, :account_holder_id, :phone_pago_movil
            )
            ON DUPLICATE KEY UPDATE
                commission_rate = VALUES(commission_rate),
                bank_id = VALUES(bank_id),
                account_number = VALUES(account_number),
                account_type = VALUES(account_type),
                account_holder_name = VALUES(account_holder_name),
                account_holder_id = VALUES(account_holder_id),
                phone_pago_movil = VALUES(phone_pago_movil),
                updated_at = CURRENT_TIMESTAMP
        ");

        $stmt->execute([
            'user_id'             => $profile->getUserId(),
            'commission_rate'     => $profile->getCommissionRate(),
            'bank_id'             => $profile->getBankId(),
            'account_number'      => $profile->getAccountNumber(),
            'account_type'        => $profile->getAccountType(),
            'account_holder_name' => $profile->getAccountHolderName(),
            'account_holder_id'   => $profile->getAccountHolderId(),
            'phone_pago_movil'    => $profile->getPhonePagoMovil(),
        ]);

        return $this->findByUserId($profile->getUserId()) ?? $profile;
    }

    private function mapRow(array $row): SellerProfile
    {
        return new SellerProfile(
            userId: (int)$row['user_id'],
            commissionRate: (float)$row['commission_rate'],
            bankId: $row['bank_id'] !== null ? (int)$row['bank_id'] : null,
            accountNumber: $row['account_number'] !== null ? (string)$row['account_number'] : null,
            accountType: (string)($row['account_type'] ?? 'CORRIENTE'),
            accountHolderName: $row['account_holder_name'] !== null ? (string)$row['account_holder_name'] : null,
            accountHolderId: $row['account_holder_id'] !== null ? (string)$row['account_holder_id'] : null,
            phonePagoMovil: $row['phone_pago_movil'] !== null ? (string)$row['phone_pago_movil'] : null,
            createdAt: !empty($row['created_at']) ? new DateTimeImmutable($row['created_at']) : null,
            updatedAt: !empty($row['updated_at']) ? new DateTimeImmutable($row['updated_at']) : null
        );
    }
}
