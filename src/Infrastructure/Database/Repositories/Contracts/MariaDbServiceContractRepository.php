<?php

namespace App\Infrastructure\Database\Repositories\Contracts;

use App\Domain\Contracts\Entities\ServiceContract;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Contracts\Repositories\ServiceContractRepositoryInterface;
use App\Domain\Shared\ValueObjects\Uuid;
use DateTimeImmutable;
use PDO;

class MariaDbServiceContractRepository implements ServiceContractRepositoryInterface
{
    public function __construct(private PDO $db) {}

    public function findById(int $id): ?ServiceContract
    {
        $stmt = $this->db->prepare("SELECT * FROM service_contracts WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    public function findByUuid(string $uuid): ?ServiceContract
    {
        $stmt = $this->db->prepare("SELECT * FROM service_contracts WHERE uuid = :uuid LIMIT 1");
        $stmt->execute(['uuid' => trim($uuid)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    public function findByClientId(int $clientId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM service_contracts
            WHERE client_id = :client_id
            ORDER BY id DESC
        ");
        $stmt->execute(['client_id' => $clientId]);

        $contracts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $contracts[] = $this->mapRow($row);
        }
        return $contracts;
    }

    public function findBySellerId(int $sellerId, ?string $status = null): array
    {
        $sql = "SELECT * FROM service_contracts WHERE seller_id = :seller_id";
        $params = ['seller_id' => $sellerId];

        if ($status !== null && trim($status) !== '') {
            $sql .= " AND status = :status";
            $params['status'] = strtoupper(trim($status));
        }

        $sql .= " ORDER BY id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $contracts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $contracts[] = $this->mapRow($row);
        }
        return $contracts;
    }

    public function findActiveByVehicleId(int $vehicleId): ?ServiceContract
    {
        $stmt = $this->db->prepare("
            SELECT * FROM service_contracts
            WHERE vehicle_id = :vehicle_id
              AND status = 'ACTIVE'
              AND (expires_at IS NULL OR expires_at >= NOW())
              AND remaining_services > 0
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute(['vehicle_id' => $vehicleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    public function findOverdueActiveContracts(): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM service_contracts
            WHERE status = 'ACTIVE'
              AND expires_at IS NOT NULL
              AND expires_at < NOW()
            ORDER BY expires_at ASC
        ");
        $stmt->execute();

        $contracts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $contracts[] = $this->mapRow($row);
        }
        return $contracts;
    }

    public function expireOverdueContracts(): int
    {
        $stmt = $this->db->prepare("
            UPDATE service_contracts
            SET status = 'EXPIRED'
            WHERE status = 'ACTIVE'
              AND expires_at IS NOT NULL
              AND expires_at < NOW()
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function save(ServiceContract $contract): ServiceContract
    {
        if ($contract->getId() === null) {
            $stmt = $this->db->prepare("
                INSERT INTO service_contracts (
                    uuid, client_id, seller_id, plan_id, vehicle_id,
                    remaining_services, remaining_km, starts_at, expires_at, status
                ) VALUES (
                    :uuid, :client_id, :seller_id, :plan_id, :vehicle_id,
                    :remaining_services, :remaining_km, :starts_at, :expires_at, :status
                )
            ");
            $stmt->execute([
                'uuid'               => $contract->getUuid()->getValue(),
                'client_id'          => $contract->getClientId(),
                'seller_id'          => $contract->getSellerId(),
                'plan_id'            => $contract->getPlanId(),
                'vehicle_id'         => $contract->getVehicleId(),
                'remaining_services' => $contract->getRemainingServices(),
                'remaining_km'       => $contract->getRemainingKm(),
                'starts_at'          => $contract->getStartsAt()?->format('Y-m-d H:i:s'),
                'expires_at'         => $contract->getExpiresAt()?->format('Y-m-d H:i:s'),
                'status'             => $contract->getStatus()->getValue(),
            ]);
            $contract->setId((int)$this->db->lastInsertId());
            return $contract;
        }

        $stmt = $this->db->prepare("
            UPDATE service_contracts
            SET seller_id = :seller_id,
                remaining_services = :remaining_services,
                remaining_km = :remaining_km,
                starts_at = :starts_at,
                expires_at = :expires_at,
                status = :status,
                vehicle_id = :vehicle_id
            WHERE id = :id
        ");
        $stmt->execute([
            'id'                 => $contract->getId(),
            'seller_id'          => $contract->getSellerId(),
            'remaining_services' => $contract->getRemainingServices(),
            'remaining_km'       => $contract->getRemainingKm(),
            'starts_at'          => $contract->getStartsAt()?->format('Y-m-d H:i:s'),
            'expires_at'         => $contract->getExpiresAt()?->format('Y-m-d H:i:s'),
            'status'             => $contract->getStatus()->getValue(),
            'vehicle_id'         => $contract->getVehicleId(),
        ]);

        return $contract;
    }

    private function mapRow(array $row): ServiceContract
    {
        return new ServiceContract(
            clientId: (int)$row['client_id'],
            planId: (int)$row['plan_id'],
            remainingServices: (int)$row['remaining_services'],
            remainingKm: (float)$row['remaining_km'],
            vehicleId: $row['vehicle_id'] !== null ? (int)$row['vehicle_id'] : null,
            status: ContractStatus::fromString((string)$row['status']),
            expiresAt: !empty($row['expires_at']) ? new DateTimeImmutable((string)$row['expires_at']) : null,
            uuid: new Uuid((string)$row['uuid']),
            id: (int)$row['id'],
            createdAt: !empty($row['created_at']) ? new DateTimeImmutable((string)$row['created_at']) : null,
            startsAt: !empty($row['starts_at']) ? new DateTimeImmutable((string)$row['starts_at']) : null,
            sellerId: isset($row['seller_id']) && $row['seller_id'] !== null ? (int)$row['seller_id'] : null
        );
    }
}
