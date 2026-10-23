<?php

namespace App\Domain\Towing\Entities;

use App\Domain\Shared\ValueObjects\Coordinates;
use DateTimeImmutable;

//Es una entidad inmutable de registro (append-only).
class TowServiceLog
{
    public function __construct(
        private ?int $id,
        private int $towServiceId,
        private Coordinates $coordinates,
        private float $speedKmh,
        private ?DateTimeImmutable $createdAt
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getTowServiceId(): int
    {
        return $this->towServiceId;
    }

    public function getCoordinates(): Coordinates
    {
        return $this->coordinates;
    }

    public function getSpeedKmh(): float
    {
        return $this->speedKmh;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public static function record(
        int $towServiceId,
        Coordinates $coordinates,
        float $speedKmh
    ): self {
        return new self(
            id: null,
            towServiceId: $towServiceId,
            coordinates: $coordinates,
            speedKmh: $speedKmh,
            createdAt: new DateTimeImmutable()
        );
    }

}