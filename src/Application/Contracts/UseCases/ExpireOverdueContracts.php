<?php

namespace App\Application\Contracts\UseCases;

use App\Domain\Contracts\Repositories\ServiceContractRepositoryInterface;
use DateTimeImmutable;

/**
 * Caso de Uso ejecutado por el Cronjob diario (y endpoint administrativo)
 * para pasar automáticamente a EXPIRED todos los contratos cuya fecha expires_at ya venció.
 */
class ExpireOverdueContracts
{
    public function __construct(
        private ServiceContractRepositoryInterface $contractRepository
    ) {}

    /**
     * @return array{expired_count: int, executed_at: string}
     */
    public function execute(): array
    {
        $expiredCount = $this->contractRepository->expireOverdueContracts();

        return [
            'expired_count' => $expiredCount,
            'executed_at'   => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
    }
}
