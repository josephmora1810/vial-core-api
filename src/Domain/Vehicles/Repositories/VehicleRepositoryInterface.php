<?php

namespace App\Domain\Vehicles\Repositories;

use App\Domain\Vehicles\Entities\Vehicle;
use App\Domain\Vehicles\ValueObjects\Niv;
use App\Domain\Vehicles\ValueObjects\PlateNumber;
use App\Domain\Shared\ValueObjects\Uuid;

interface VehicleRepositoryInterface
{
    public function save(Vehicle $vehicle): void;

    public function findById(int $id): ?Vehicle;

    public function findByUuid(Uuid $uuid): ?Vehicle;

    public function findByPlateNumber(PlateNumber $plateNumber): ?Vehicle;

    public function findByNiv(Niv $niv): ?Vehicle;

    /**
     * @return Vehicle[]
     */
    public function findAllByUserId(int $userId): array;

    /**
     * @return Vehicle[]
     */
    public function findCranesByUserId(int $userId): array;

    public function softDeleteByUuid(Uuid $uuid, int $userId): bool;

    /**
     * Consulta la vista vw_vehicle_details por UUID público
     * @return array<string, mixed>|null
     */
    public function findDetailedByUuid(Uuid $uuid): ?array;

    /**
     * Consulta la vista vw_vehicle_details por Placa única
     * @return array<string, mixed>|null
     */
    public function findDetailedByPlateNumber(PlateNumber $plateNumber): ?array;

    /**
     * Consulta la vista vw_vehicle_details para todos los vehículos del usuario
     * @return array<array<string, mixed>>
     */
    public function findDetailedByUserId(int $userId): array;
}
