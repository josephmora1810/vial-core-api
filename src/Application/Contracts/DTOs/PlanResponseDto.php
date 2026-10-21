<?php

namespace App\Application\Contracts\DTOs;

use App\Domain\Contracts\Entities\Plan;

readonly class PlanResponseDto
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public float $price_usd,
        public float $exchange_rate,
        public float $price_ves,
        public int $duration_months,
        public int $max_services,
        public float $max_km,
        public bool $is_active
    ) {}

    public static function fromEntity(Plan $plan, float $exchangeRate): self
    {
        return new self(
            id: $plan->getUuid()->getValue(),
            name: $plan->getName(),
            description: $plan->getDescription(),
            price_usd: $plan->getPriceUsd()->getAmount(),
            exchange_rate: $exchangeRate,
            price_ves: $plan->calculatePriceInVes($exchangeRate)->getAmount(),
            duration_months: $plan->getDurationMonths(),
            max_services: $plan->getMaxServices(),
            max_km: $plan->getMaxKm(),
            is_active: $plan->isActive()
        );
    }

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'description'     => $this->description,
            'price_usd'       => $this->price_usd,
            'exchange_rate'   => $this->exchange_rate,
            'price_ves'       => $this->price_ves,
            'duration_months' => $this->duration_months,
            'max_services'    => $this->max_services,
            'max_km'          => $this->max_km,
            'is_active'       => $this->is_active,
        ];
    }
}
