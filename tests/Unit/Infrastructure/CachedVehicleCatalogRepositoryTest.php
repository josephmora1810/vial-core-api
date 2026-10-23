<?php

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Database\Redis;
use App\Infrastructure\Database\Repositories\Vehicles\CachedVehicleCatalogRepository;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryVehicleCatalogRepository;

class CachedVehicleCatalogRepositoryTest extends TestCase
{
    private InMemoryVehicleCatalogRepository $inner;

    protected function setUp(): void
    {
        $this->inner = new InMemoryVehicleCatalogRepository();
    }

    public function test_get_classes_caches_on_miss_and_serves_from_cache(): void
    {
        $storage = [];
        $redis = $this->createStub(Redis::class);

        $redis->method('execute')
            ->willReturnCallback(function (string $cmd, ...$args) use (&$storage) {
                if ($cmd === 'GET') {
                    return $storage[$args[0]] ?? null;
                }
                if ($cmd === 'SET') {
                    $storage[$args[0]] = $args[1];
                    return 'OK';
                }
                if ($cmd === 'EXPIRE') {
                    return 1;
                }
                return null;
            });

        $cachedRepo = new CachedVehicleCatalogRepository($this->inner, $redis);

        // 1st call: Miss -> pulls 3 classes from inner and populates Redis storage
        $first = $cachedRepo->getClasses();
        $this->assertCount(3, $first);
        $this->assertArrayHasKey('catalog:vehicle:classes', $storage);

        // Modify inner to prove second call hits Redis cache instead of inner repo
        $this->inner->classes = [];

        // 2nd call: Hit -> returns the cached 3 classes from Redis
        $second = $cachedRepo->getClasses();
        $this->assertCount(3, $second);
        $this->assertSame('AUTOMOVIL', $second[0]['code']);
    }

    public function test_get_brands_caches_all_and_filters_in_ram(): void
    {
        $redis = $this->createStub(Redis::class);

        $redis->method('execute')
            ->willReturnCallback(function (string $cmd, ...$args) {
                if ($cmd === 'GET' && $args[0] === 'catalog:vehicle:brands:all') {
                    return json_encode([
                        ['id' => 1, 'name' => 'Toyota'],
                        ['id' => 2, 'name' => 'Chevrolet'],
                        ['id' => 3, 'name' => 'Ford']
                    ]);
                }
                return 'OK';
            });

        $cachedRepo = new CachedVehicleCatalogRepository($this->inner, $redis);

        // Filtering with 'toy' should return Toyota
        $results = $cachedRepo->getBrands('toy');
        $this->assertCount(1, $results);
        $this->assertSame('Toyota', $results[0]['name']);

        // Filtering with non-existent brand
        $empty = $cachedRepo->getBrands('Ferrari');
        $this->assertEmpty($empty);
    }

    public function test_find_brand_by_name_fuzzy_substring(): void
    {
        $redis = $this->createStub(Redis::class);
        $redis->method('execute')->willReturn(null);

        $cachedRepo = new CachedVehicleCatalogRepository($this->inner, $redis);

        // Substring match: OCR returned "TOYOTA DE VENEZUELA C.A."
        $brand = $cachedRepo->findBrandByName('TOYOTA DE VENEZUELA C.A.');
        $this->assertNotNull($brand);
        $this->assertSame('Toyota', $brand['name']);
    }

    public function test_get_models_by_brand_and_ram_search(): void
    {
        $redis = $this->createStub(Redis::class);
        $redis->method('execute')->willReturn(null);

        $cachedRepo = new CachedVehicleCatalogRepository($this->inner, $redis);

        // Brand 1 is Toyota, has Corolla and Hilux
        $allModels = $cachedRepo->getModelsByBrand(1);
        $this->assertCount(2, $allModels);

        $filtered = $cachedRepo->getModelsByBrand(1, 'hilux');
        $this->assertCount(1, $filtered);
        $this->assertSame('Hilux', $filtered[0]['name']);
    }

    public function test_find_model_by_name_and_brand(): void
    {
        $redis = $this->createStub(Redis::class);
        $redis->method('execute')->willReturn(null);

        $cachedRepo = new CachedVehicleCatalogRepository($this->inner, $redis);

        // Exact match
        $model = $cachedRepo->findModelByNameAndBrand('Corolla', 1);
        $this->assertNotNull($model);
        $this->assertSame('Corolla', $model['name']);

        // Substring match: "COROLLA GLI 1.8"
        $fuzzyModel = $cachedRepo->findModelByNameAndBrand('COROLLA GLI 1.8', 1);
        $this->assertNotNull($fuzzyModel);
        $this->assertSame('Corolla', $fuzzyModel['name']);
    }

    public function test_fallback_silently_when_redis_is_null(): void
    {
        $cachedRepo = new CachedVehicleCatalogRepository($this->inner, null);

        $classes = $cachedRepo->getClasses();
        $this->assertCount(3, $classes);

        $brands = $cachedRepo->getBrands('chevrolet');
        $this->assertCount(1, $brands);
        $this->assertSame('Chevrolet', $brands[0]['name']);
    }

    public function test_fallback_silently_when_redis_throws_exception(): void
    {
        $redis = $this->createStub(Redis::class);
        $redis->method('execute')->willThrowException(new Exception('Connection refused'));

        $cachedRepo = new CachedVehicleCatalogRepository($this->inner, $redis);

        // Should not throw exception, instead fall back to inner
        $classes = $cachedRepo->getClasses();
        $this->assertCount(3, $classes);

        $brands = $cachedRepo->getBrands();
        $this->assertCount(count($this->inner->brands), $brands);
    }
}
