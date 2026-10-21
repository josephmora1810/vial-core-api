<?php

namespace Tests\Doubles;

use App\Domain\Vehicles\Repositories\VehicleCatalogRepositoryInterface;

class InMemoryVehicleCatalogRepository implements VehicleCatalogRepositoryInterface
{
    public array $classes = [
        ['id' => 1, 'code' => 'AUTOMOVIL', 'name' => 'Automovil', 'description' => 'Sedanes y compactos'],
        ['id' => 2, 'code' => 'CAMIONETA', 'name' => 'Camioneta', 'description' => 'SUV y Pick-up'],
        ['id' => 3, 'code' => 'MOTO',      'name' => 'Moto',      'description' => 'Motocicletas'],
    ];

    public array $brands = [
        ['id' => 1, 'name' => 'Toyota'],
        ['id' => 2, 'name' => 'Chevrolet'],
        ['id' => 3, 'name' => 'Ford'],
    ];

    public array $models = [
        ['id' => 1, 'brand_id' => 1, 'vehicle_class_id' => 1, 'name' => 'Corolla'],
        ['id' => 2, 'brand_id' => 1, 'vehicle_class_id' => 2, 'name' => 'Hilux'],
        ['id' => 3, 'brand_id' => 2, 'vehicle_class_id' => 1, 'name' => 'Aveo'],
        ['id' => 4, 'brand_id' => 2, 'vehicle_class_id' => 1, 'name' => 'Corsa'],
    ];

    public function getClasses(): array
    {
        return $this->classes;
    }

    public function getSubclasses(?int $classId = null): array
    {
        return [];
    }

    public function getBrands(?string $search = null): array
    {
        if ($search) {
            return array_filter($this->brands, fn($b) => stripos($b['name'], $search) !== false);
        }
        return $this->brands;
    }

    public function findBrandByName(string $name): ?array
    {
        foreach ($this->brands as $brand) {
            if (strcasecmp($brand['name'], $name) === 0 || stripos($name, $brand['name']) !== false) {
                return $brand;
            }
        }
        return null;
    }

    public function getModelsByBrand(int $brandId, ?string $search = null): array
    {
        $filtered = array_filter($this->models, fn($m) => $m['brand_id'] === $brandId);
        if ($search) {
            $filtered = array_filter($filtered, fn($m) => stripos($m['name'], $search) !== false);
        }
        return array_values($filtered);
    }

    public function findModelByNameAndBrand(string $modelName, int $brandId): ?array
    {
        foreach ($this->models as $model) {
            if ($model['brand_id'] === $brandId && (strcasecmp($model['name'], $modelName) === 0 || stripos($modelName, $model['name']) !== false)) {
                return $model;
            }
        }
        return null;
    }
}
