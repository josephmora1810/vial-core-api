<?php

namespace Tests\Unit\Domain\Services;

use App\Domain\Vehicles\Services\VehicleMatcherService;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryVehicleCatalogRepository;

class VehicleMatcherServiceTest extends TestCase
{
    private VehicleMatcherService $service;
    private InMemoryVehicleCatalogRepository $catalogRepo;

    protected function setUp(): void
    {
        $this->catalogRepo = new InMemoryVehicleCatalogRepository();
        $this->service = new VehicleMatcherService($this->catalogRepo);
    }

    public function test_matches_brand_and_model_and_infers_class(): void
    {
        $rawOcr = [
            'plate_number'  => 'ab123cd',
            'niv'           => '8x123456789012345',
            'engine_serial' => 'eng112233',
            'brand'         => 'TOYOTA',
            'model'         => 'COROLLA',
            'year'          => 2015,
            'color'         => 'AZUL NOCHE',
        ];

        $matched = $this->service->match($rawOcr);

        $this->assertTrue($matched['is_matched']);
        $this->assertEquals('AB123CD', $matched['plate_number']);
        $this->assertEquals(1, $matched['brand_id']);
        $this->assertEquals('Toyota', $matched['brand_name']);
        $this->assertEquals(1, $matched['model_id']);
        $this->assertEquals('Corolla', $matched['model_name']);
        $this->assertEquals(1, $matched['vehicle_class_id']); // Automovil
        $this->assertEquals('Azul Noche', $matched['color']);
    }

    public function test_handles_unknown_brand_gracefully(): void
    {
        $rawOcr = [
            'plate_number' => 'XY999ZZ',
            'brand'        => 'MARCA_DESCONOCIDA_XYZ',
            'model'        => 'MODELO_XYZ',
        ];

        $matched = $this->service->match($rawOcr);

        $this->assertFalse($matched['is_matched']);
        $this->assertNull($matched['brand_id']);
        $this->assertEquals('MARCA_DESCONOCIDA_XYZ', $matched['brand_name']);
        $this->assertNull($matched['model_id']);
        $this->assertEquals('MODELO_XYZ', $matched['model_name']);
    }
}
