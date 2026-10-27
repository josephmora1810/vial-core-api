<?php

declare(strict_types=1);

namespace App\Domain\Payments\Services;

interface BankAuthServiceInterface
{
    public function getAccessToken(bool $forceRefresh = false): string;

    public function requestNewAccessToken(): string;
}
