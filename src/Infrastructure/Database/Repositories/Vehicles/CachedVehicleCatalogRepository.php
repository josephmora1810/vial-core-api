<?php

namespace App\Infrastructure\Database\Repositories\Vehicles;

use App\Domain\Shared\Services\RedisClientInterface;
use App\Domain\Vehicles\Repositories\VehicleCatalogRepositoryInterface;
use Throwable;

/**
 * Decorador de Caché en Redis para el catálogo maestro de vehículos (Marcas, Modelos, Clases).
 * Implementa el patrón Cache-Aside con tolerancia a fallos (Fallback a base de datos relacional).
 */
class CachedVehicleCatalogRepository implements VehicleCatalogRepositoryInterface
{
    private const DEFAULT_TTL = 86400; // 24 horas

    public function __construct(
        private VehicleCatalogRepositoryInterface $inner,
        private ?RedisClientInterface $redis = null,
        private int $ttlSeconds = self::DEFAULT_TTL
    ) {}

    public function getClasses(): array
    {
        $key = 'catalog:vehicle:classes';

        $cached = $this->getFromCache($key);
        if ($cached !== null) {
            return $cached;
        }

        $classes = $this->inner->getClasses();
        $this->saveToCache($key, $classes);

        return $classes;
    }

    public function getSubclasses(?int $classId = null): array
    {
        $key = 'catalog:vehicle:subclasses:' . ($classId ?? 'all');

        $cached = $this->getFromCache($key);
        if ($cached !== null) {
            return $cached;
        }

        $subclasses = $this->inner->getSubclasses($classId);
        $this->saveToCache($key, $subclasses);

        return $subclasses;
    }

    public function getBrands(?string $search = null): array
    {
        $allBrandsKey = 'catalog:vehicle:brands:all';

        // 1. Obtener la lista maestra completa de marcas (desde Redis o MariaDB)
        $allBrands = $this->getFromCache($allBrandsKey);
        if ($allBrands === null) {
            $allBrands = $this->inner->getBrands(null);
            $this->saveToCache($allBrandsKey, $allBrands);
        }

        // 2. Si hay filtro de búsqueda, filtrar en memoria RAM al instante (< 0.1ms, cero consultas SQL)
        if (!empty($search)) {
            $term = trim($search);
            $filtered = array_filter($allBrands, function ($b) use ($term) {
                return stripos($b['name'], $term) !== false;
            });

            return array_slice(array_values($filtered), 0, 50);
        }

        return $allBrands;
    }

    public function findBrandByName(string $name): ?array
    {
        $allBrandsKey = 'catalog:vehicle:brands:all';
        $allBrands = $this->getFromCache($allBrandsKey);

        if ($allBrands === null) {
            $allBrands = $this->inner->getBrands(null);
            $this->saveToCache($allBrandsKey, $allBrands);
        }

        $cleanName = trim($name);

        // Coincidencia exacta
        foreach ($allBrands as $b) {
            if (strcasecmp($b['name'], $cleanName) === 0) {
                return $b;
            }
        }

        // Coincidencia por subcadena (ej: "TOYOTA DE VENEZUELA" -> "Toyota"), priorizando mayor longitud
        $sortedBrands = $allBrands;
        usort($sortedBrands, static fn(array $a, array $b): int => strlen($b['name']) <=> strlen($a['name']));
        foreach ($sortedBrands as $b) {
            if ($b['name'] !== '' && stripos($cleanName, $b['name']) !== false) {
                return $b;
            }
        }

        return $this->inner->findBrandByName($name);
    }

    public function getModelsByBrand(int $brandId, ?string $search = null): array
    {
        $brandModelsKey = 'catalog:vehicle:models:brand:' . $brandId;

        // 1. Obtener todos los modelos de la marca desde Redis o MariaDB
        $allModels = $this->getFromCache($brandModelsKey);
        if ($allModels === null) {
            $allModels = $this->inner->getModelsByBrand($brandId, null);
            $this->saveToCache($brandModelsKey, $allModels);
        }

        // 2. Si hay término de búsqueda, filtrar en memoria RAM
        if (!empty($search)) {
            $term = trim($search);
            $filtered = array_filter($allModels, function ($m) use ($term) {
                return stripos($m['name'], $term) !== false;
            });

            return array_values($filtered);
        }

        return $allModels;
    }

    public function findModelByNameAndBrand(string $modelName, int $brandId): ?array
    {
        $brandModelsKey = 'catalog:vehicle:models:brand:' . $brandId;
        $allModels = $this->getFromCache($brandModelsKey);

        if ($allModels === null) {
            $allModels = $this->inner->getModelsByBrand($brandId, null);
            $this->saveToCache($brandModelsKey, $allModels);
        }

        $cleanModel = trim($modelName);

        // Coincidencia exacta
        foreach ($allModels as $m) {
            if (strcasecmp($m['name'], $cleanModel) === 0) {
                return $m;
            }
        }

        // Coincidencia por subcadena (ej: "COROLLA GLI" -> "Corolla"), priorizando mayor longitud
        $sortedModels = $allModels;
        usort($sortedModels, static fn(array $a, array $b): int => strlen($b['name']) <=> strlen($a['name']));
        foreach ($sortedModels as $m) {
            if ($m['name'] !== '' && stripos($cleanModel, $m['name']) !== false) {
                return $m;
            }
        }

        return $this->inner->findModelByNameAndBrand($modelName, $brandId);
    }

    // --- Helpers de Caché con Resiliencia ---

    private function getFromCache(string $key): ?array
    {
        if ($this->redis === null) {
            return null;
        }

        try {
            $data = $this->redis->execute('GET', $key);
            if (!empty($data) && is_string($data)) {
                $decoded = json_decode($data, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        } catch (Throwable) {
            // Si Redis está caído, fallback silencioso a MariaDB
            return null;
        }

        return null;
    }

    private function saveToCache(string $key, array $data): void
    {
        if ($this->redis === null) {
            return;
        }

        try {
            $json = json_encode($data, JSON_UNESCAPED_UNICODE);
            $this->redis->execute('SET', $key, $json);
            $this->redis->execute('EXPIRE', $key, (string)$this->ttlSeconds);
        } catch (Throwable) {
            // Ignorar fallo de escritura en Redis; la app sigue operando normalmente
        }
    }
}
