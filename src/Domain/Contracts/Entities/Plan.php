<?php

namespace App\Domain\Contracts\Entities;

use App\Domain\Shared\Enums\Currency;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Shared\ValueObjects\Uuid;
use DateTimeImmutable;
use InvalidArgumentException;

class Plan
{
    private Uuid $uuid;
    private Money $priceUsd;
    private DateTimeImmutable $createdAt;

    public function __construct(
        private string $name,
        Money|float $priceUsd,
        private int $durationMonths = 12,
        private int $maxServices = 2,
        private float $maxKm = 100.00,
        private ?string $description = null,
        private bool $isActive = true,
        ?Uuid $uuid = null,
        private ?int $id = null,
        ?DateTimeImmutable $createdAt = null
    ) {
        $this->name = trim($this->name);
        if ($this->name === '') {
            throw new InvalidArgumentException('El nombre del plan no puede estar vacío.');
        }
        if ($this->durationMonths <= 0) {
            throw new InvalidArgumentException('La vigencia del plan en meses debe ser mayor a 0.');
        }
        if ($this->maxServices <= 0) {
            throw new InvalidArgumentException('El límite de servicios (max_services) debe ser mayor a 0.');
        }
        if ($this->maxKm <= 0) {
            throw new InvalidArgumentException('El kilometraje incluido (max_km) debe ser mayor a 0.');
        }

        $this->priceUsd = $priceUsd instanceof Money
            ? $priceUsd
            : new Money((float)$priceUsd, Currency::USD);

        if ($this->priceUsd->getAmount() <= 0) {
            throw new InvalidArgumentException('El precio del plan en USD debe ser mayor a 0.');
        }

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

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPriceUsd(): Money
    {
        return $this->priceUsd;
    }

    public function getDurationMonths(): int
    {
        return $this->durationMonths;
    }

    public function getMaxServices(): int
    {
        return $this->maxServices;
    }

    public function getMaxKm(): float
    {
        return $this->maxKm;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function calculatePriceInVes(float $exchangeRate): Money
    {
        if ($exchangeRate <= 0) {
            throw new InvalidArgumentException('La tasa de cambio debe ser mayor a 0.');
        }
        $vesAmount = round($this->priceUsd->getAmount() * $exchangeRate, 2);
        return new Money($vesAmount, Currency::VES);
    }

    public function updateDetails(
        string $name,
        ?string $description,
        float $priceUsd,
        int $durationMonths,
        int $maxServices,
        float $maxKm,
        bool $isActive = true
    ): void {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('El nombre del plan no puede estar vacío.');
        }
        if ($priceUsd <= 0) {
            throw new InvalidArgumentException('El precio del plan en USD debe ser mayor a 0.');
        }
        if ($durationMonths <= 0 || $maxServices <= 0 || $maxKm <= 0) {
            throw new InvalidArgumentException('La duración, cantidad de servicios y kilómetros deben ser mayores a 0.');
        }

        $this->name = $name;
        $this->description = $description;
        $this->priceUsd = new Money($priceUsd, Currency::USD);
        $this->durationMonths = $durationMonths;
        $this->maxServices = $maxServices;
        $this->maxKm = $maxKm;
        $this->isActive = $isActive;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }
}
