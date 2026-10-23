<?php

namespace App\Infrastructure\Database\Repositories\Towing;

use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Towing\Entities\TowService;
use App\Domain\Towing\Enums\TowServiceStatus;
use App\Domain\Towing\Repositories\TowServiceRepositoryInterface;
use DateTimeImmutable;
use PDO;

class MariaDbTowServiceRepository implements TowServiceRepositoryInterface
{
    public function __construct(private PDO $db) {}

    public function save(TowService $service): void
    {
        if ($service->getId() === null) {
            $sql = "
                INSERT INTO tow_services (
                    uuid,
                    client_id,
                    driver_id,
                    vehicle_id,
                    contract_id,
                    origin_lat,
                    origin_lng,
                    origin_address,
                    dest_lat,
                    dest_lng,
                    dest_address,
                    saved_address_id,
                    failure_description,
                    estimated_distance_km,
                    extra_km,
                    extra_order_id,
                    status,
                    cancel_reason,
                    cancelled_by,
                    accepted_at,
                    arrived_at,
                    completed_at,
                    cancelled_at,
                    created_at,
                    updated_at
                ) VALUES (
                    :uuid,
                    :client_id,
                    :driver_id,
                    :vehicle_id,
                    :contract_id,
                    :origin_lat,
                    :origin_lng,
                    :origin_address,
                    :dest_lat,
                    :dest_lng,
                    :dest_address,
                    :saved_address_id,
                    :failure_description,
                    :estimated_distance_km,
                    :extra_km,
                    :extra_order_id,
                    :status,
                    :cancel_reason,
                    :cancelled_by,
                    :accepted_at,
                    :arrived_at,
                    :completed_at,
                    :cancelled_at,
                    NOW(),
                    NOW()
                )
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':uuid'                  => $service->getUuid()->getValue(),
                ':client_id'             => $service->getClientId(),
                ':driver_id'             => $service->getDriverId(),
                ':vehicle_id'            => $service->getVehicleId(),
                ':contract_id'           => $service->getContractId(),
                ':origin_lat'            => $service->getOrigin()->getLatitude(),
                ':origin_lng'            => $service->getOrigin()->getLongitude(),
                ':origin_address'        => $service->getOriginAddress(),
                ':dest_lat'              => $service->getDestination()->getLatitude(),
                ':dest_lng'              => $service->getDestination()->getLongitude(),
                ':dest_address'          => $service->getDestinationAddress(),
                ':saved_address_id'      => $service->getSavedAddressId(),
                ':failure_description'   => $service->getFailureDescription(),
                ':estimated_distance_km' => $service->getEstimatedDistanceKm(),
                ':extra_km'              => $service->getExtraKm(),
                ':extra_order_id'        => $service->getExtraOrderId(),
                ':status'                => $service->getStatus()->value,
                ':cancel_reason'         => $service->getCancellationReason(),
                ':cancelled_by'          => $service->getCancelledBy(),
                ':accepted_at'           => $service->getAcceptedAt()?->format('Y-m-d H:i:s'),
                ':arrived_at'            => $service->getArrivedAt()?->format('Y-m-d H:i:s'),
                ':completed_at'          => $service->getCompletedAt()?->format('Y-m-d H:i:s'),
                ':cancelled_at'          => $service->getCancelledAt()?->format('Y-m-d H:i:s'),
            ]);
            $service->setId((int)$this->db->lastInsertId());
        } else {
            $sql = "
                UPDATE tow_services SET
                    client_id             = :client_id,
                    driver_id             = :driver_id,
                    vehicle_id            = :vehicle_id,
                    contract_id           = :contract_id,
                    origin_lat            = :origin_lat,
                    origin_lng            = :origin_lng,
                    origin_address        = :origin_address,
                    dest_lat              = :dest_lat,
                    dest_lng              = :dest_lng,
                    dest_address          = :dest_address,
                    saved_address_id      = :saved_address_id,
                    failure_description   = :failure_description,
                    estimated_distance_km = :estimated_distance_km,
                    extra_km              = :extra_km,
                    extra_order_id        = :extra_order_id,
                    status                = :status,
                    cancel_reason         = :cancel_reason,
                    cancelled_by          = :cancelled_by,
                    accepted_at           = :accepted_at,
                    arrived_at            = :arrived_at,
                    completed_at          = :completed_at,
                    cancelled_at          = :cancelled_at,
                    updated_at            = NOW()
                WHERE id = :id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id'                    => $service->getId(),
                ':client_id'             => $service->getClientId(),
                ':driver_id'             => $service->getDriverId(),
                ':vehicle_id'            => $service->getVehicleId(),
                ':contract_id'           => $service->getContractId(),
                ':origin_lat'            => $service->getOrigin()->getLatitude(),
                ':origin_lng'            => $service->getOrigin()->getLongitude(),
                ':origin_address'        => $service->getOriginAddress(),
                ':dest_lat'              => $service->getDestination()->getLatitude(),
                ':dest_lng'              => $service->getDestination()->getLongitude(),
                ':dest_address'          => $service->getDestinationAddress(),
                ':saved_address_id'      => $service->getSavedAddressId(),
                ':failure_description'   => $service->getFailureDescription(),
                ':estimated_distance_km' => $service->getEstimatedDistanceKm(),
                ':extra_km'              => $service->getExtraKm(),
                ':extra_order_id'        => $service->getExtraOrderId(),
                ':status'                => $service->getStatus()->value,
                ':cancel_reason'         => $service->getCancellationReason(),
                ':cancelled_by'          => $service->getCancelledBy(),
                ':accepted_at'           => $service->getAcceptedAt()?->format('Y-m-d H:i:s'),
                ':arrived_at'            => $service->getArrivedAt()?->format('Y-m-d H:i:s'),
                ':completed_at'          => $service->getCompletedAt()?->format('Y-m-d H:i:s'),
                ':cancelled_at'          => $service->getCancelledAt()?->format('Y-m-d H:i:s'),
            ]);
        }
    }

    public function findById(int $id): ?TowService
    {
        $sql = "SELECT * FROM tow_services WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    public function findByUuid(Uuid $uuid): ?TowService
    {
        $sql = "SELECT * FROM tow_services WHERE uuid = :uuid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':uuid' => $uuid->getValue()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    public function findActiveByClientId(int $clientId): ?TowService
    {
        $sql = "
            SELECT * FROM tow_services
            WHERE client_id = :client_id
              AND status IN (:pending, :in_route, :in_progress)
            ORDER BY id DESC
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':client_id'   => $clientId,
            ':pending'     => TowServiceStatus::Pending->value,
            ':in_route'    => TowServiceStatus::InRoute->value,
            ':in_progress' => TowServiceStatus::InProgress->value,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    public function findActiveByDriverId(int $driverId): ?TowService
    {
        $sql = "
            SELECT * FROM tow_services
            WHERE driver_id = :driver_id
              AND status IN (:in_route, :in_progress)
            ORDER BY id DESC
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':driver_id'   => $driverId,
            ':in_route'    => TowServiceStatus::InRoute->value,
            ':in_progress' => TowServiceStatus::InProgress->value,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    public function lockForUpdate(int $id): ?TowService
    {
        $sql = "SELECT * FROM tow_services WHERE id = :id FOR UPDATE";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    public function findPendingNearby(Coordinates $center, float $radiusKm = 25.0): array
    {
        $sql = "
            SELECT *, 
                (6371 * acos(
                    cos(radians(:lat1)) * cos(radians(origin_lat)) * cos(radians(origin_lng) - radians(:lng)) + 
                    sin(radians(:lat2)) * sin(radians(origin_lat))
                )) AS distance
            FROM tow_services
            WHERE status = :status
            HAVING distance <= :radius
            ORDER BY distance ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':status' => TowServiceStatus::Pending->value,
            ':lat1'   => $center->getLatitude(),
            ':lat2'   => $center->getLatitude(),
            ':lng'    => $center->getLongitude(),
            ':radius' => $radiusKm,
        ]);

        $services = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $services[] = $this->mapRow($row);
        }
        return $services;
    }

    /**
     * Retorna solicitudes en PENDING que superaron el tiempo límite de espera sin asignación (FR-24-C).
     *
     * @return list<TowService>
     */
    public function findOverduePendingServices(int $timeoutMinutes = 10): array
    {
        $sql = "
            SELECT * FROM tow_services
            WHERE status = :status
              AND created_at <= (NOW() - INTERVAL :minutes MINUTE)
            ORDER BY created_at ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':status', TowServiceStatus::Pending->value, PDO::PARAM_STR);
        $stmt->bindValue(':minutes', $timeoutMinutes, PDO::PARAM_INT);
        $stmt->execute();

        $services = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $services[] = $this->mapRow($row);
        }
        return $services;
    }

    private function mapRow(array $row): TowService
    {
        return new TowService(
            id: (int)$row['id'],
            uuid: Uuid::fromString($row['uuid']),
            clientId: (int)$row['client_id'],
            driverId: $row['driver_id'] !== null ? (int)$row['driver_id'] : null,
            vehicleId: (int)$row['vehicle_id'],
            contractId: $row['contract_id'] !== null ? (int)$row['contract_id'] : null,
            origin: new Coordinates((float)$row['origin_lat'], (float)$row['origin_lng']),
            originAddress: (string)$row['origin_address'],
            destination: new Coordinates((float)$row['dest_lat'], (float)$row['dest_lng']),
            destinationAddress: (string)$row['dest_address'],
            savedAddressId: $row['saved_address_id'] !== null ? (int)$row['saved_address_id'] : null,
            failureDescription: (string)$row['failure_description'],
            estimatedDistanceKm: (float)$row['estimated_distance_km'],
            extraKm: (float)$row['extra_km'],
            extraOrderId: $row['extra_order_id'] !== null ? (int)$row['extra_order_id'] : null,
            status: TowServiceStatus::from($row['status']),
            cancellationReason: $row['cancel_reason'] ?? null,
            cancelledBy: $row['cancelled_by'] ?? null,
            acceptedAt: !empty($row['accepted_at']) ? new DateTimeImmutable($row['accepted_at']) : null,
            arrivedAt: !empty($row['arrived_at']) ? new DateTimeImmutable($row['arrived_at']) : null,
            completedAt: !empty($row['completed_at']) ? new DateTimeImmutable($row['completed_at']) : null,
            cancelledAt: !empty($row['cancelled_at']) ? new DateTimeImmutable($row['cancelled_at']) : null,
            createdAt: !empty($row['created_at']) ? new DateTimeImmutable($row['created_at']) : null,
            updatedAt: !empty($row['updated_at']) ? new DateTimeImmutable($row['updated_at']) : null
        );
    }
}
