<?php

namespace App\Application\Contracts\UseCases;

use App\Application\Contracts\DTOs\PlanResponseDto;
use App\Domain\Contracts\Repositories\PlanRepositoryInterface;
use App\Domain\Payments\Services\ExchangeRateServiceInterface;

class ListPlans
{
    public function __construct(
        private PlanRepositoryInterface $planRepository,
        private ExchangeRateServiceInterface $exchangeRateService
    ) {}

    /**
     * @return array{exchange_rate_usd_ves: float, plans: list<array<string, mixed>>}
     */
    public function execute(bool $onlyActive = true): array
    {
        $exchangeRate = $this->exchangeRateService->getExchangeRate('USD', 'venta');
        $plans = $this->planRepository->findAll($onlyActive);

        $items = [];
        foreach ($plans as $plan) {
            $items[] = PlanResponseDto::fromEntity($plan, $exchangeRate)->toArray();
        }

        return [
            'exchange_rate_usd_ves' => $exchangeRate,
            'plans'                 => $items,
        ];
    }
}
