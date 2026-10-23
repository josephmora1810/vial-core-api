<?php

namespace App\Infrastructure\Services\Towing;

use App\Domain\Shared\ValueObjects\Coordinates;
use App\Domain\Towing\Repositories\CoverageZoneRepositoryInterface;
use App\Domain\Towing\ValueObjects\CoverageZone;

class InMemoryCoverageZoneRepository implements CoverageZoneRepositoryInterface
{
    /** @var CoverageZone[] */
    private array $zones;
    public function __construct()
    {
        $this->zones = [
            new CoverageZone(
                code: 'CCS',
                name: 'Gran Caracas y Litoral Central',
                minCoordinates: new Coordinates(10.150000, -67.200000),
                maxCoordinates: new Coordinates(10.650000, -66.400000)
            ),
        ];
    }

    public function getActiveZones(): array
    {
        return $this->zones;
    }

    public function findMatchingZone(Coordinates $point): ?CoverageZone
    {
        foreach ($this->zones as $zone) {
            if ($zone->contains($point)) {
                return $zone;
            }
        }
        return null;
    }
}