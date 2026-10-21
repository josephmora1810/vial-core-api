<?php

namespace App\Domain\Vehicles\Repositories;

interface VehicleCatalogRepositoryInterface
{
    /**
     * @return array<array{id: int, code: string, name: string, description: ?string}>
     */
    public function getClasses(): array;

    /**
     * @return array<array{id: int, vehicle_class_id: ?int, name: string, description: ?string}>
     */
    public function getSubclasses(?int $classId = null): array;

    /**
     * @return array<array{id: int, name: string}>
     */
    public function getBrands(?string $search = null): array;

    public function findBrandByName(string $name): ?array;

    /**
     * @return array<array{id: int, brand_id: int, vehicle_class_id: ?int, name: string}>
     */
    public function getModelsByBrand(int $brandId, ?string $search = null): array;

    public function findModelByNameAndBrand(string $modelName, int $brandId): ?array;
}
