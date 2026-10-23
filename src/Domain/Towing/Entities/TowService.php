<?php

namespace App\Domain\Towing\Entities;

use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Towing\Enums\TowServiceStatus;
use DateTimeImmutable;

class TowService
{
    public function __construct(
        private ?int $id,
        private Uuid $uuid,
        private int $clientId,
        private ?int $driverId,
        private int $vehicleId,
        private ?int $contractId, // por si es SOS
        private Coordinates $origin,
        private string $originAddress,
        private Coordinates $destination,
        private string $destinationAddress,
        private ?int $savedAddressId,
        private string $failureDescription,
        private float $estimatedDistanceKm,
        private float $extraKm,
        private ?int $extraOrderId,
        private TowServiceStatus $status,
        private ?string $cancellationReason,
        private ?string $cancelledBy,
        private ?DateTimeImmutable $acceptedAt,
        private ?DateTimeImmutable $arrivedAt,
        private ?DateTimeImmutable $completedAt,
        private ?DateTimeImmutable $cancelledAt,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt
    )
    {}

    public static function create(
        int $clientId,
        int $vehicleId,
        Coordinates $origin,
        string $originAddress,
        Coordinates $destination,
        string $destinationAddress,
        string $failureDescription,
        float $estimatedDistanceKm,
        ?int $contractId = null,
        ?int $savedAddressId = null,
        float $extraKm = 0.0,
        ?int $extraOrderId = null
    ): self {
        return new self(
            id: null,
            uuid: Uuid::generate(),
            clientId: $clientId,
            driverId: null,
            vehicleId: $vehicleId,
            contractId: $contractId,
            origin: $origin,
            originAddress: $originAddress,
            destination: $destination,
            destinationAddress: $destinationAddress,
            savedAddressId: $savedAddressId,
            failureDescription: $failureDescription,
            estimatedDistanceKm: $estimatedDistanceKm,
            extraKm: $extraKm,
            extraOrderId: $extraOrderId,
            status: TowServiceStatus::Pending,
            cancellationReason: null,
            cancelledBy: null,
            acceptedAt: null,
            arrivedAt: null,
            completedAt: null,
            cancelledAt: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable()
        );
    }

    public function accept(int $driverId): void
    {
        if ($this->status !== TowServiceStatus::Pending) {
            throw new \LogicException('El servicio no está en estado PENDING y no puede ser aceptado.');
        }
        $this->driverId = $driverId;
        $this->status = TowServiceStatus::InRoute;
        $this->acceptedAt = new DateTimeImmutable();
    }

    public function arrive(): void
    {
        if ($this->status !== TowServiceStatus::InRoute) {
            throw new \LogicException('El servicio no está en estado IN_ROUTE y no puede marcarse como ARRIVED.');
        }
        $this->status = TowServiceStatus::InProgress;
        $this->arrivedAt = new DateTimeImmutable();
    }

    public function complete(): void
    {
        if ($this->status !== TowServiceStatus::InProgress) {
            throw new \LogicException('El servicio no está en estado IN_PROGRESS y no puede marcarse como COMPLETED.');
        }
        $this->status = TowServiceStatus::Completed;
        $this->completedAt = new DateTimeImmutable();
    }

    public function cancelByClient(string $reason): void
    {
        if ($this->status === TowServiceStatus::Completed || $this->status === TowServiceStatus::CancelledByClient || $this->status === TowServiceStatus::CancelledByDriver) {
            throw new \LogicException('El servicio no puede ser cancelado por el cliente en su estado actual.');
        }
        $this->status = TowServiceStatus::CancelledByClient;
        $this->cancellationReason = $reason;
        $this->cancelledBy = 'CLIENT';
        $this->cancelledAt = new DateTimeImmutable();
    }

    public function cancelByDriver(string $reason): void
    {
        if ($this->status !== TowServiceStatus::InRoute && $this->status !== TowServiceStatus::InProgress) {
            throw new \DomainException('El conductor solo puede cancelar servicios que tenga asignados en curso.');
        }
        $this->status = TowServiceStatus::CancelledByDriver;
        $this->cancellationReason = $reason;
        $this->cancelledBy = 'DRIVER';
        $this->cancelledAt = new DateTimeImmutable();
    }

    public function reopenForReassignment(string $reason): void
    {
        if ($this->status !== TowServiceStatus::InRoute) {
            throw new \DomainException('Solo se puede reasignar un servicio que se encuentre en ruta.');
        }
        $this->status = TowServiceStatus::Pending;
        $this->driverId = null;
        $this->acceptedAt = null;
        $this->cancellationReason = 'Reasignado por incidente del conductor: ' . $reason;
        $this->cancelledBy = 'DRIVER';
        $this->updatedAt = new DateTimeImmutable();
    }

    public function cancelByTimeout(): void
    {
        if ($this->status !== TowServiceStatus::Pending) {
            throw new \LogicException('El servicio no puede ser cancelado por timeout en su estado actual.');
        }
        $this->status = TowServiceStatus::CancelledByTimeout;
        $this->cancellationReason = 'Tiempo de espera excedido sin aceptación del conductor.';
        $this->cancelledBy = 'SYSTEM_TIMEOUT';
        $this->cancelledAt = new DateTimeImmutable();
    }

    public function assignExtraOrder(int $orderId): void
    {
        $this->extraOrderId = $orderId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function setExtraOrderId(?int $extraOrderId): void
    {
        $this->extraOrderId = $extraOrderId;
        $this->updatedAt = new DateTimeImmutable();
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

    public function getDriverId(): ?int
    {
        return $this->driverId;
    }

    public function getVehicleId(): int
    {
        return $this->vehicleId;
    }

    public function getContractId(): ?int
    {
        return $this->contractId;
    }

    public function getOrigin(): Coordinates
    {
        return $this->origin;
    }

    public function getOriginAddress(): string
    {
        return $this->originAddress;
    }

    public function getDestination(): Coordinates
    {
        return $this->destination;
    }

    public function getDestinationAddress(): string
    {
        return $this->destinationAddress;
    }

    public function getSavedAddressId(): ?int
    {
        return $this->savedAddressId;
    }

    public function getFailureDescription(): string
    {
        return $this->failureDescription;
    }

    public function getEstimatedDistanceKm(): float
    {
        return $this->estimatedDistanceKm;
    }

    public function getExtraKm(): float
    {
        return $this->extraKm;
    }

    public function getExtraOrderId(): ?int
    {
        return $this->extraOrderId;
    }

    public function getStatus(): TowServiceStatus
    {
        return $this->status;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function getCancelledBy(): ?string
    {
        return $this->cancelledBy;
    }

    public function getAcceptedAt(): ?DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function getArrivedAt(): ?DateTimeImmutable
    {
        return $this->arrivedAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getCancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
