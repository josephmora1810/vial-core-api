<?php

namespace App\Domain\Vehicles\Entities;

use App\Domain\Vehicles\Enums\VehicleStatus;
use App\Domain\Vehicles\Enums\VehicleType;
use App\Domain\Vehicles\ValueObjects\EngineSerial;
use App\Domain\Vehicles\ValueObjects\Niv;
use App\Domain\Vehicles\ValueObjects\PlateNumber;
use App\Domain\Shared\ValueObjects\Uuid;
use DateTimeImmutable;

class Vehicle
{
    public function __construct(
        private ?int $id,
        private Uuid $uuid,
        private int $userId,
        private PlateNumber $plateNumber,
        private Niv $niv,
        private ?EngineSerial $engineSerial,
        private int $year,
        private string $color,
        private ?int $vehicleClassId = null,
        private ?int $vehicleSubclassId = null,
        private ?int $brandId = null,
        private ?int $modelId = null,
        private ?string $brandName = null,
        private ?string $modelName = null,
        private VehicleType $type = VehicleType::Car,
        private VehicleStatus $status = VehicleStatus::Pending,
        private bool $isVerified = false,
        private ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null,
        private ?DateTimeImmutable $deletedAt = null
    ) {
        $this->createdAt ??= new DateTimeImmutable();
    }

    public static function create(
        int $userId,
        PlateNumber $plateNumber,
        Niv $niv,
        ?EngineSerial $engineSerial,
        int $year,
        string $color,
        ?int $vehicleClassId = null,
        ?int $vehicleSubclassId = null,
        ?int $brandId = null,
        ?int $modelId = null,
        ?string $brandName = null,
        ?string $modelName = null,
        bool $isVerified = false,
        ?VehicleStatus $status = null,
        ?Uuid $uuid = null,
        ?VehicleType $type = null
    ): self {
        return new self(
            id: null,
            uuid: $uuid ?? Uuid::generate(),
            userId: $userId,
            plateNumber: $plateNumber,
            niv: $niv,
            engineSerial: $engineSerial,
            year: $year,
            color: trim($color),
            vehicleClassId: $vehicleClassId,
            vehicleSubclassId: $vehicleSubclassId,
            brandId: $brandId,
            modelId: $modelId,
            brandName: $brandName ? trim($brandName) : null,
            modelName: $modelName ? trim($modelName) : null,
            type: $type ?? VehicleType::Car,
            status: $status ?? ($isVerified ? VehicleStatus::Active : VehicleStatus::Pending),
            isVerified: $isVerified,
            createdAt: new DateTimeImmutable()
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getPlateNumber(): PlateNumber
    {
        return $this->plateNumber;
    }

    public function getNiv(): Niv
    {
        return $this->niv;
    }

    public function getEngineSerial(): ?EngineSerial
    {
        return $this->engineSerial;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): self
    {
        $this->color = trim($color);
        $this->touch();
        return $this;
    }

    public function getVehicleClassId(): ?int
    {
        return $this->vehicleClassId;
    }

    public function setVehicleClassId(?int $vehicleClassId): self
    {
        $this->vehicleClassId = $vehicleClassId;
        $this->touch();
        return $this;
    }

    public function getVehicleSubclassId(): ?int
    {
        return $this->vehicleSubclassId;
    }

    public function setVehicleSubclassId(?int $vehicleSubclassId): self
    {
        $this->vehicleSubclassId = $vehicleSubclassId;
        $this->touch();
        return $this;
    }

    public function getBrandId(): ?int
    {
        return $this->brandId;
    }

    public function setBrandId(?int $brandId): self
    {
        $this->brandId = $brandId;
        $this->touch();
        return $this;
    }

    public function getModelId(): ?int
    {
        return $this->modelId;
    }

    public function setModelId(?int $modelId): self
    {
        $this->modelId = $modelId;
        $this->touch();
        return $this;
    }

    public function getBrandName(): ?string
    {
        return $this->brandName;
    }

    public function setBrandName(?string $brandName): self
    {
        $this->brandName = $brandName ? trim($brandName) : null;
        $this->touch();
        return $this;
    }

    public function getModelName(): ?string
    {
        return $this->modelName;
    }

    public function setModelName(?string $modelName): self
    {
        $this->modelName = $modelName ? trim($modelName) : null;
        $this->touch();
        return $this;
    }

    public function getStatus(): VehicleStatus
    {
        return $this->status;
    }

    public function setStatus(VehicleStatus $status): self
    {
        $this->status = $status;
        $this->touch();
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function isActive(): bool
    {
        return $this->status === VehicleStatus::Active && !$this->isDeleted();
    }

    public function verify(): self
    {
        $this->isVerified = true;
        $this->status = VehicleStatus::Active;
        $this->touch();
        return $this;
    }

    public function unverify(): self
    {
        $this->isVerified = false;
        $this->status = VehicleStatus::Pending;
        $this->touch();
        return $this;
    }

    public function activate(): self
    {
        $this->status = VehicleStatus::Active;
        $this->touch();
        return $this;
    }

    public function markAsOccupied(): self
    {
        $this->status = VehicleStatus::Occupied;
        $this->touch();
        return $this;
    }

    public function deactivate(): self
    {
        $this->status = VehicleStatus::Disabled;
        $this->touch();
        return $this;
    }

    public function softDelete(): self
    {
        $this->deletedAt = new DateTimeImmutable();
        $this->status = VehicleStatus::Disabled;
        $this->touch();
        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function getType(): VehicleType
    {
        return $this->type;
    }

    public function setType(VehicleType $type): self
    {
        $this->type = $type;
        $this->touch();
        return $this;
    }

    public function isCrane(): bool
    {
        return $this->type->isCrane();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
