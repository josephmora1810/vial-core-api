<?php

namespace App\Domain\Contracts\Entities;

use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Shared\ValueObjects\Uuid;
use DateTimeImmutable;
use InvalidArgumentException;

class ServiceContract
{
    private Uuid $uuid;
    private ContractStatus $status;
    private DateTimeImmutable $createdAt;

    public function __construct(
        private int $clientId,
        private int $planId,
        private int $remainingServices,
        private float $remainingKm,
        private ?int $vehicleId = null,
        ContractStatus|string $status = ContractStatus::PendingPayment,
        private ?DateTimeImmutable $expiresAt = null,
        ?Uuid $uuid = null,
        private ?int $id = null,
        ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $startsAt = null,
        private ?int $sellerId = null,
        private ?string $verificationCode = null,
        private ?string $integrityHash = null
    ) {
        if ($this->remainingServices < 0) {
            throw new InvalidArgumentException('Los servicios restantes no pueden ser negativos.');
        }
        if ($this->remainingKm < 0) {
            throw new InvalidArgumentException('El kilometraje restante no puede ser negativo.');
        }

        $this->status = $status instanceof ContractStatus ? $status : ContractStatus::fromString($status);
        $this->uuid = $uuid ?? Uuid::v7();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getClientId(): int
    {
        return $this->clientId;
    }

    public function getSellerId(): ?int
    {
        return $this->sellerId;
    }

    public function setSellerId(?int $sellerId): void
    {
        $this->sellerId = $sellerId;
    }

    public function getPlanId(): int
    {
        return $this->planId;
    }

    public function getVehicleId(): ?int
    {
        return $this->vehicleId;
    }

    public function getRemainingServices(): int
    {
        return $this->remainingServices;
    }

    public function getRemainingKm(): float
    {
        return $this->remainingKm;
    }

    public function getStartsAt(): ?DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getStatus(): ContractStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Activa o renueva el contrato por los meses del plan,
     * restaurando los cupos de servicios y kilometraje cuando se suministran.
     */
    public function activate(
        int $durationMonths = 12,
        ?DateTimeImmutable $activatedAt = null,
        ?int $resetServices = null,
        ?float $resetKm = null
    ): void {
        if ($durationMonths <= 0) {
            throw new InvalidArgumentException('La duración en meses debe ser positiva.');
        }
        $now = $activatedAt ?? new DateTimeImmutable();

        // Si es una renovación oportuna mientras el contrato aún no ha vencido, mantiene continuidad sumando meses al vencimiento actual
        if ($this->status === ContractStatus::Active && $this->expiresAt !== null && $this->expiresAt > $now) {
            $this->expiresAt = $this->expiresAt->modify("+{$durationMonths} months");
        } else {
            $this->startsAt = $now;
            $this->expiresAt = $now->modify("+{$durationMonths} months");
        }

        $this->status = ContractStatus::Active;

        if ($resetServices !== null && $resetServices >= 0) {
            $this->remainingServices = $resetServices;
        }
        if ($resetKm !== null && $resetKm >= 0) {
            $this->remainingKm = $resetKm;
        }
    }

    public function renew(
        int $maxServices,
        float $maxKm,
        int $durationMonths = 12,
        ?DateTimeImmutable $renewedAt = null
    ): void {
        $this->activate($durationMonths, $renewedAt, $maxServices, $maxKm);
    }

    public function cancel(): void
    {
        $this->status = ContractStatus::Cancelled;
    }

    public function expire(): void
    {
        $this->status = ContractStatus::Expired;
    }

    public function isActive(): bool
    {
        if ($this->status !== ContractStatus::Active) {
            return false;
        }
        if ($this->expiresAt !== null && $this->expiresAt < new DateTimeImmutable()) {
            return false;
        }
        return true;
    }

    public function canCoverRescue(): bool
    {
        return $this->isActive() && $this->remainingServices > 0;
    }

    public function consumeService(float $distanceKm = 0.0): void
    {
        if (!$this->canCoverRescue()) {
            throw new InvalidArgumentException('El contrato no está activo o no posee servicios disponibles.');
        }
        $this->remainingServices--;
        $this->remainingKm = max(0.0, round($this->remainingKm - max(0.0, $distanceKm), 2));
    }

    public function refundService(float $distanceKm = 0.0): void
    {
        $this->remainingServices++;
        $this->remainingKm = round($this->remainingKm + max(0.0, $distanceKm), 2);
    }

    public function getVerificationCode(): ?string
    {
        return $this->verificationCode;
    }

    public function setVerificationCode(string $verificationCode): void
    {
        $this->verificationCode = strtoupper(trim($verificationCode));
    }

    public function getIntegrityHash(): ?string
    {
        return $this->integrityHash;
    }

    public function setIntegrityHash(string $integrityHash): void
    {
        $this->integrityHash = strtolower(trim($integrityHash));
    }

    /**
     * Genera un código de verificación alfanumérico público único para el contrato.
     * Formato: UBKXXXX-YYYY-ZZZZ (donde XXXX es MMYY, ej: UBK1026-8F2A-99C1)
     */
    public function generateVerificationCode(?DateTimeImmutable $date = null): string
    {
        if ($this->verificationCode !== null && $this->verificationCode !== '') {
            return $this->verificationCode;
        }

        $refDate = $date ?? $this->startsAt ?? $this->createdAt ?? new DateTimeImmutable();
        $mmyy = $refDate->format('my'); // mes y año en 2 dígitos cada uno (ej: 1026)

        $part1 = strtoupper(substr(hash('crc32b', $this->uuid->getValue() . ($this->id ?? 0)), 0, 4));
        $part2 = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        $this->verificationCode = "UBK{$mmyy}-{$part1}-{$part2}";
        return $this->verificationCode;
    }

    /**
     * Calcula la firma HMAC SHA-256 de integridad interna del contrato.
     */
    public function calculateIntegrityHash(string $secretKey, ?string $plateNumber = null): string
    {
        $payload = implode('|', [
            (string)($this->id ?? 0),
            $this->uuid->getValue(),
            (string)$this->clientId,
            (string)$this->planId,
            (string)($this->vehicleId ?? 0),
            strtoupper(trim((string)$plateNumber)),
            $this->startsAt?->format('Y-m-d') ?? '',
            $this->expiresAt?->format('Y-m-d') ?? '',
            $this->status->getValue(),
            $this->verificationCode ?? '',
        ]);

        $this->integrityHash = hash_hmac('sha256', $payload, $secretKey);
        return $this->integrityHash;
    }
}
