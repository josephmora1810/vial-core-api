<?php

namespace App\Domain\Commissions\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

class SellerProfile
{
    public function __construct(
        private int $userId,
        private float $commissionRate = 10.00,
        private ?int $bankId = null,
        private ?string $accountNumber = null,
        private string $accountType = 'CORRIENTE',
        private ?string $accountHolderName = null,
        private ?string $accountHolderId = null,
        private ?string $phonePagoMovil = null,
        private ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null
    ) {
        $this->validateCommissionRate($commissionRate);
        $this->accountType = strtoupper(trim($this->accountType));
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getCommissionRate(): float
    {
        return $this->commissionRate;
    }

    public function setCommissionRate(float $rate): void
    {
        $this->validateCommissionRate($rate);
        $this->commissionRate = round($rate, 2);
    }

    public function getBankId(): ?int
    {
        return $this->bankId;
    }

    public function getAccountNumber(): ?string
    {
        return $this->accountNumber;
    }

    public function getAccountType(): string
    {
        return $this->accountType;
    }

    public function getAccountHolderName(): ?string
    {
        return $this->accountHolderName;
    }

    public function getAccountHolderId(): ?string
    {
        return $this->accountHolderId;
    }

    public function getPhonePagoMovil(): ?string
    {
        return $this->phonePagoMovil;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updateBankDetails(
        int $bankId,
        string $accountNumber,
        string $accountType,
        string $accountHolderName,
        string $accountHolderId,
        string $phonePagoMovil
    ): void {
        $cleanAccount = preg_replace('/\D/', '', $accountNumber);
        if (strlen($cleanAccount) !== 20) {
            throw new InvalidArgumentException('El número de cuenta bancaria nacional debe contener exactamente 20 dígitos numéricos.');
        }

        $cleanType = strtoupper(trim($accountType));
        if (!in_array($cleanType, ['CORRIENTE', 'AHORRO'], true)) {
            throw new InvalidArgumentException('El tipo de cuenta debe ser CORRIENTE o AHORRO.');
        }

        $cleanHolderName = trim($accountHolderName);
        if (empty($cleanHolderName)) {
            throw new InvalidArgumentException('El nombre o razón social del titular es obligatorio.');
        }

        $cleanHolderId = strtoupper(trim($accountHolderId));
        if (!preg_match('/^[VJEG]-?\d{6,10}(-\d)?$/i', $cleanHolderId)) {
            throw new InvalidArgumentException('El documento de identidad o RIF del titular no tiene un formato válido (ej. V-12345678 o J-12345678-0).');
        }

        $cleanPhone = preg_replace('/\D/', '', $phonePagoMovil);
        if (strlen($cleanPhone) < 10 || strlen($cleanPhone) > 12) {
            throw new InvalidArgumentException('El teléfono asociado a Pago Móvil debe contener entre 10 y 11 dígitos.');
        }

        $this->bankId = $bankId;
        $this->accountNumber = $cleanAccount;
        $this->accountType = $cleanType;
        $this->accountHolderName = $cleanHolderName;
        $this->accountHolderId = $cleanHolderId;
        $this->phonePagoMovil = $cleanPhone;
    }

    public function hasCompleteBankDetails(): bool
    {
        return $this->bankId !== null
            && !empty($this->accountNumber)
            && strlen($this->accountNumber) === 20
            && !empty($this->accountHolderName)
            && !empty($this->accountHolderId)
            && !empty($this->phonePagoMovil);
    }

    private function validateCommissionRate(float $rate): void
    {
        if ($rate < 0.0 || $rate > 100.0) {
            throw new InvalidArgumentException('El porcentaje de comisión debe estar comprendido entre 0.00% y 100.00%.');
        }
    }

    public function toArray(): array
    {
        return [
            'user_id'             => $this->userId,
            'commission_rate'     => $this->commissionRate,
            'bank_id'             => $this->bankId,
            'account_number'      => $this->accountNumber,
            'account_type'        => $this->accountType,
            'account_holder_name' => $this->accountHolderName,
            'account_holder_id'   => $this->accountHolderId,
            'phone_pago_movil'    => $this->phonePagoMovil,
            'has_bank_details'    => $this->hasCompleteBankDetails(),
            'created_at'          => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at'          => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
