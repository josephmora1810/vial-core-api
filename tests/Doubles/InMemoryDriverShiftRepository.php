<?php

namespace Tests\Doubles;

use App\Domain\Driver\Entities\DriverShift;
use App\Domain\Driver\Repositories\DriverShiftRepositoryInterface;

class InMemoryDriverShiftRepository implements DriverShiftRepositoryInterface
{
    /** @var array<int, DriverShift> */
    public array $shifts = [];
    private int $autoIncrement = 1;

    public function save(DriverShift $shift): DriverShift
    {
        if ($shift->getId() === null) {
            $id = $this->autoIncrement++;
            $saved = new DriverShift(
                id: $id,
                driverId: $shift->getDriverId(),
                craneId: $shift->getCraneId(),
                isActive: $shift->isActive(),
                startedAt: $shift->getStartedAt(),
                endedAt: $shift->getEndedAt(),
                lastLatitude: $shift->getLastLatitude(),
                lastLongitude: $shift->getLastLongitude(),
                lastPingAt: $shift->getLastPingAt()
            );
            $this->shifts[$id] = $saved;
            return $saved;
        }

        $this->shifts[$shift->getId()] = $shift;
        return $shift;
    }

    public function findActiveByDriverId(int $driverId): ?DriverShift
    {
        foreach ($this->shifts as $shift) {
            if ($shift->getDriverId() === $driverId && $shift->isActive()) {
                return $shift;
            }
        }
        return null;
    }

    public function findActiveByCraneId(int $craneId): ?DriverShift
    {
        foreach ($this->shifts as $shift) {
            if ($shift->getCraneId() === $craneId && $shift->isActive()) {
                return $shift;
            }
        }
        return null;
    }

    public function endActiveShift(int $driverId): void
    {
        foreach ($this->shifts as $shift) {
            if ($shift->getDriverId() === $driverId && $shift->isActive()) {
                $shift->end();
            }
        }
    }

    public function updateLocation(int $driverId, float $latitude, float $longitude): void
    {
        $shift = $this->findActiveByDriverId($driverId);
        $shift?->updateCoordinates($latitude, $longitude);
    }

    public function findNearbyActiveDrivers(float $latitude, float $longitude, float $radiusKm = 25.0): array
    {
        $results = [];
        foreach ($this->shifts as $shift) {
            if (!$shift->isActive() || $shift->getLastLatitude() === null || $shift->getLastLongitude() === null) {
                continue;
            }

            // Haversine
            $latFrom = deg2rad($latitude);
            $lonFrom = deg2rad($longitude);
            $latTo = deg2rad($shift->getLastLatitude());
            $lonTo = deg2rad($shift->getLastLongitude());

            $latDelta = $latTo - $latFrom;
            $lonDelta = $lonTo - $lonFrom;

            $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
                cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
            $distance = $angle * 6371;

            if ($distance <= $radiusKm) {
                $results[] = [
                    'shift_id'        => $shift->getId(),
                    'driver_id'       => $shift->getDriverId(),
                    'crane_id'        => $shift->getCraneId(),
                    'last_latitude'   => $shift->getLastLatitude(),
                    'last_longitude'  => $shift->getLastLongitude(),
                    'distance_km'     => round($distance, 2),
                    'driver_names'    => 'Chofer',
                    'driver_surnames' => 'Prueba',
                    'crane_plate'     => 'CRANE01',
                ];
            }
        }

        return $results;
    }
}
