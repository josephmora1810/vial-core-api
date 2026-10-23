<?php

namespace App\Domain\Driver\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

class DriverShift
{
    public function __construct(
        private ?int $id,
        private int $driverId,
        private int $craneId,
        private bool $isActive = true,
        private DateTimeImmutable $startedAt = new DateTimeImmutable(),
        private ?DateTimeImmutable $endedAt = null,
        private ?float $lastLatitude = null,
        private ?float $lastLongitude = null,
        private ?DateTimeImmutable $lastPingAt = null
    ) {}

    public static function start(int $driverId, int $craneId): self
    {
        return new self(
            id: null,
            driverId: $driverId,
            craneId: $craneId,
            isActive: true,
            startedAt: new DateTimeImmutable()
        );
    }

    public function end(): void
    {
        $this->isActive = false;
        $this->endedAt = new DateTimeImmutable();
    }

    public function updateCoordinates(float $latitude, float $longitude): void
    {
        $this->lastLatitude = $latitude;
        $this->lastLongitude = $longitude;
        $this->lastPingAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDriverId(): int
    {
        return $this->driverId;
    }

    public function getCraneId(): int
    {
        return $this->craneId;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getEndedAt(): ?DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function getLastLatitude(): ?float
    {
        return $this->lastLatitude;
    }

    public function getLastLongitude(): ?float
    {
        return $this->lastLongitude;
    }

    public function getLastPingAt(): ?DateTimeImmutable
    {
        return $this->lastPingAt;
    }
}
