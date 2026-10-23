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

    public function test_ocr_typo_berr_does_not_hot_create_model_and_returns_unmatched_for_manual_form(): void
    {
        // Si el OCR leyó "Berr" en lugar de "Bera", el servicio no debe inventar la marca ni el modelo
        $rawOcr = [
            'plate_number' => 'AB1C23D',
            'niv'          => '8XBERR1500000001',
            'brand'        => 'Berr',
            'model'        => '150',
            'year'         => 2023,
            'color'        => 'Azul',
        ];

        $matched = $this->service->match($rawOcr);

        $this->assertFalse($matched['is_matched']);
        $this->assertNull($matched['brand_id']);
        $this->assertSame('Berr', $matched['brand_name']);
        $this->assertNull($matched['model_id']);
        $this->assertSame('150', $matched['model_name']);
        $this->assertNull($matched['vehicle_class_id']);
    }

    public function test_matches_valid_bera_150_when_ocr_is_clean(): void
    {
        $rawOcr = [
            'plate_number' => 'AB1C23D',
            'niv'          => '8XBERA1500000001',
            'brand'        => 'Bera',
            'model'        => '150',
            'year'         => 2023,
            'color'        => 'Negro',
        ];

        $matched = $this->service->match($rawOcr);

        $this->assertTrue($matched['is_matched']);
        $this->assertSame(4, $matched['brand_id']);
        $this->assertSame('Bera', $matched['brand_name']);
        $this->assertSame(5, $matched['model_id']);
        $this->assertSame('150', $matched['model_name']);
        $this->assertSame(3, $matched['vehicle_class_id']); // Moto
    }

    public function test_carnet_scan_without_niv_preserves_null_niv(): void
    {
        $rawOcr = [
            'plate_number' => 'AC456DF',
            'niv'          => null, // Carnet donde no aparece el NIV
            'brand'        => 'Toyota',
            'model'        => 'Corolla',
            'year'         => 2018,
            'color'        => 'Blanco',
        ];

        $matched = $this->service->match($rawOcr);

        $this->assertTrue($matched['is_matched']);
        $this->assertSame('AC456DF', $matched['plate_number']);
        $this->assertNull($matched['niv']);
    }

    public function test_niv_certificate_scan_without_plate_preserves_null_plate(): void
    {
        $rawOcr = [
            'plate_number' => null, // Certificado NIV sin placa asignada aún
            'niv'          => '8X123456789012345',
            'brand'        => 'Toyota',
            'model'        => 'Corolla',
            'year'         => 2024,
            'color'        => 'Gris',
        ];

        $matched = $this->service->match($rawOcr);

        $this->assertTrue($matched['is_matched']);
        $this->assertNull($matched['plate_number']);
        $this->assertSame('8X123456789012345', $matched['niv']);
    }
}
