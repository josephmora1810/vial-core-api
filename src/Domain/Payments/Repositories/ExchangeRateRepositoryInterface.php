<?php

namespace App\Domain\Payments\Repositories;

use App\Domain\Payments\Entities\ExchangeRate;
use DateTimeImmutable;

interface ExchangeRateRepositoryInterface
{
    public function findByDate(string $currency, string $type, DateTimeImmutable $date): ?ExchangeRate;

    public function findLatest(string $currency, string $type, ?DateTimeImmutable $sinceDate = null): ?ExchangeRate;

    /** @return list<ExchangeRate> */
    public function findLatestAll(): array;

    public function upsert(ExchangeRate $exchangeRate): void;
}
