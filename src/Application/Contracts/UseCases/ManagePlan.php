<?php

namespace App\Application\Contracts\UseCases;

use App\Application\Contracts\DTOs\PlanResponseDto;
use App\Domain\Contracts\Entities\Plan;
use App\Domain\Contracts\Repositories\PlanRepositoryInterface;
use App\Domain\Payments\Services\ExchangeRateServiceInterface;
use Exception;

class ManagePlan
{
    public function __construct(
        private PlanRepositoryInterface $planRepository,
        private ExchangeRateServiceInterface $exchangeRateService
    ) {}

    public function create(
        string $name,
        float $priceUsd,
        int $durationMonths = 12,
        int $maxServices = 2,
        float $maxKm = 100.00,
        ?string $description = null
    ): array {
        $plan = new Plan(
            name: $name,
            priceUsd: $priceUsd,
            durationMonths: $durationMonths,
            maxServices: $maxServices,
            maxKm: $maxKm,
            description: $description,
            isActive: true
        );

        $saved = $this->planRepository->save($plan);
        $rate = $this->exchangeRateService->getExchangeRate('USD', 'venta');

        return PlanResponseDto::fromEntity($saved, $rate)->toArray();
    }

    public function update(
        string $uuid,
        string $name,
        float $priceUsd,
        int $durationMonths,
        int $maxServices,
        float $maxKm,
        ?string $description = null,
        bool $isActive = true
    ): array {
        $plan = $this->planRepository->findByUuid($uuid);
        if ($plan === null) {
            throw new Exception('El plan solicitado no existe.', 404);
        }

        $plan->updateDetails(
            name: $name,
            description: $description,
            priceUsd: $priceUsd,
            durationMonths: $durationMonths,
            maxServices: $maxServices,
            maxKm: $maxKm,
            isActive: $isActive
        );

        $saved = $this->planRepository->save($plan);
        $rate = $this->exchangeRateService->getExchangeRate('USD', 'venta');

        return PlanResponseDto::fromEntity($saved, $rate)->toArray();
    }

    public function deactivate(string $uuid): void
    {
        $plan = $this->planRepository->findByUuid($uuid);
        if ($plan === null) {
            throw new Exception('El plan solicitado no existe.', 404);
        }

        $plan->deactivate();
        $this->planRepository->save($plan);
    }
}
