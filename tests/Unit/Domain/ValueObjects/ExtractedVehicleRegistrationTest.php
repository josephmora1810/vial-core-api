<?php

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\Auth\ValueObjects\ExtractedVehicleRegistration;
use PHPUnit\Framework\TestCase;

class ExtractedVehicleRegistrationTest extends TestCase
{
    public function testExtractedVehicleRegistrationValueObject(): void
    {
        $dto = new ExtractedVehicleRegistration(
            documentType: 'vehicle_registration',
            plateNumber: 'AB123CD',
            brand: 'Toyota',
            model: 'Corolla',
            year: 2018,
            color: 'Blanco',
            engineSerial: 'ENG-998877',
            niv: 'NIV1234567890ABC',
            vehicleClass: 'Automóvil',
            ownerNationalId: 'V-12345678',
            ownerName: 'JUAN PEREZ',
            confidence: 0.98,
            warnings: ['Baja iluminación en esquina'],
            rawPayload: ['raw' => true]
        );

        $this->assertSame('vehicle_registration', $dto->getDocumentType());
        $this->assertSame('AB123CD', $dto->getPlateNumber());
        $this->assertSame('Toyota', $dto->getBrand());
        $this->assertSame('Corolla', $dto->getModel());
        $this->assertSame(2018, $dto->getYear());
        $this->assertSame('Blanco', $dto->getColor());
        $this->assertSame('ENG-998877', $dto->getEngineSerial());
        $this->assertSame('NIV1234567890ABC', $dto->getNiv());
        $this->assertSame('Automóvil', $dto->getVehicleClass());
        $this->assertSame('V-12345678', $dto->getOwnerNationalId());
        $this->assertSame('JUAN PEREZ', $dto->getOwnerName());
        $this->assertSame(0.98, $dto->getConfidence());
        $this->assertSame(['Baja iluminación en esquina'], $dto->getWarnings());
        $this->assertSame(['raw' => true], $dto->getRawPayload());

        $array = $dto->toArray();
        $this->assertSame('AB123CD', $array['plate_number']);
        $this->assertSame('Toyota', $array['brand']);
        $this->assertSame('Corolla', $array['model']);
    }
}
