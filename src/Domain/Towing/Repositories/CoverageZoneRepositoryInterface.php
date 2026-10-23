<?php

namespace App\Domain\Towing\Repositories;

use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Towing\ValueObjects\CoverageZone;

interface CoverageZoneRepositoryInterface
{
    /**
     * @return CoverageZone[]
     */
    public function getActiveZones(): array;

    /**
     * Retorna la zona que cubre las coordenadas dadas, o null si está fuera de cobertura.
     */
    public function findMatchingZone(Coordinates $point): ?CoverageZone;
}