<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTOs;

use App\Application\Vehicles\DTOs\{
    VehicleDeletedResponseDto,
    VehicleDetailedResponseDto,
    VehicleResponseDto,
    VehicleScanResponseDto
};
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Vehicles\Entities\Vehicle;
use App\Domain\Vehicles\Enums\{VehicleStatus, VehicleType};
use App\Domain\Vehicles\ValueObjects\{EngineSerial, Niv, PlateNumber};
use PHPUnit\Framework\TestCase;

final class VehicleDtosTest extends TestCase
{
    public function testVehicleResponseDto(): void
    {
        $vehicle = new Vehicle(
            id: 1,
            uuid: Uuid::generate(),
            userId: 5,
            plateNumber: new PlateNumber('AB123CD'),
            niv: new Niv('8X1ABCDEF12345678'),
            engineSerial: new EngineSerial('ENG999000'),
            year: 2020,
            color: 'Azul',
            vehicleClassId: 1,
            vehicleSubclassId: 2,
            modelId: 10,
            type: VehicleType::Car,
            status: VehicleStatus::Active,
            isVerified: true
        );

        $dto = VehicleResponseDto::fromEntity($vehicle, 'Toyota', 'Corolla', 'Automovil', 'Sedan');

        $this->assertSame('AB123CD', $dto->plate_number);
        $this->assertSame('8X1ABCDEF12345678', $dto->niv);
        $this->assertSame(2020, $dto->year);
        $this->assertSame('Azul', $dto->color);
        $this->assertSame('Toyota', $dto->brand);
        $this->assertSame('Corolla', $dto->model);
        $this->assertTrue($dto->is_verified);
        $this->assertArrayHasKey('uuid', $dto->toArray());
    }

    public function testVehicleDetailedResponseDto(): void
    {
        $uuid = Uuid::generate()->getValue();
        $row = [
            'uuid'             => $uuid,
            'user_id'          => 3,
            'plate_number'     => 'AB123CD',
            'niv'              => '8X1ABCDEF12345678',
            'engine_serial'    => 'ENG123',
            'year'             => 2019,
            'color'            => 'Rojo',
            'brand_name'       => 'Ford',
            'model_name'       => 'Fiesta',
            'class_name'       => 'Automovil',
            'subclass_name'    => 'Hatchback',
            'vehicle_type'     => 'CAR',
            'vehicle_status'   => 'ACTIVE',
            'is_verified'      => 1,
            'created_at'       => '2026-01-01 12:00:00',
        ];

        $dto = VehicleDetailedResponseDto::fromViewRow($row);

        $this->assertSame($uuid, $dto->uuid);
        $this->assertSame('Ford', $dto->brand);
        $this->assertSame('Fiesta', $dto->model);
        $this->assertSame(2019, $dto->year);
        $this->assertTrue($dto->is_verified);
        $this->assertSame($row['plate_number'], $dto->toArray()['plate_number']);
    }

    public function testVehicleDeletedResponseDto(): void
    {
        $dto = new VehicleDeletedResponseDto(id: 'v-uuid-456', deleted: true);

        $this->assertSame('v-uuid-456', $dto->id);
        $this->assertTrue($dto->deleted);
        $this->assertSame(['id' => 'v-uuid-456', 'deleted' => true], $dto->toArray());
    }

    public function testVehicleScanResponseDto(): void
    {
        $data = [
            'suggested_vehicle' => [
                'plate_number' => 'AB123CD',
                'brand_name'   => 'Toyota',
                'model_name'   => 'Corolla',
                'year'         => 2021,
                'color'        => 'Blanco',
            ],
            'document_type'     => 'vehicle_registration',
            'warnings'          => [],
            'raw_ocr'           => ['confidence' => 0.98],
            'file_path'         => '/storage/carnet.jpg',
            'message'           => 'Documento escaneado.',
        ];

        $dto = VehicleScanResponseDto::fromArray($data);

        $this->assertSame('vehicle_registration', $dto->document_type);
        $this->assertSame('AB123CD', $dto->suggested_vehicle['plate_number']);
        $this->assertSame($data, $dto->toArray());
    }
}
