<?php

namespace Tests\Unit\Application\DTOs;

use App\Application\Driver\DTOs\{
    DriverCraneRegistrationResponseDto,
    DriverFleetCraneStatusDto,
    DriverFleetStatusResponseDto,
    DriverLicenseResponseDto,
    DriverLocationResponseDto,
    DriverShiftActionResponseDto,
    DriverShiftResponseDto
};
use App\Domain\Driver\Entities\DriverShift;
use App\Domain\Shared\ValueObjects\{Coordinates, Uuid};
use App\Domain\Vehicles\Entities\Vehicle;
use App\Domain\Vehicles\ValueObjects\PlateNumber;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class DriverDtosTest extends TestCase
{
    public function testDriverCraneRegistrationResponseDtoFromArrayAndToArray(): void
    {
        $payload = [
            'crane'               => ['id' => 10, 'plate_number' => 'ABC123D'],
            'carnet_document_id'  => 55,
            'license_document_id' => 66,
            'is_fleet_ready'      => true,
            'status_message'      => 'Flota lista para operar.',
        ];

        $dto = DriverCraneRegistrationResponseDto::fromArray($payload);

        $this->assertSame(['id' => 10, 'plate_number' => 'ABC123D'], $dto->crane);
        $this->assertSame(55, $dto->carnetDocumentId);
        $this->assertSame(66, $dto->licenseDocumentId);
        $this->assertTrue($dto->isFleetReady);
        $this->assertSame('Flota lista para operar.', $dto->statusMessage);
        $this->assertSame($payload, $dto->toArray());
    }

    public function testDriverFleetStatusResponseDtoFromArrayAndToArray(): void
    {
        $payload = [
            'driver_id'              => 7,
            'driver_name'            => 'Pedro Conductor',
            'can_operate'            => true,
            'is_fleet_ready'         => true,
            'identity_card_status'   => 'VERIFIED',
            'driving_license_status' => 'VERIFIED',
            'cranes_count'           => 1,
            'cranes'                 => [
                [
                    'crane'            => ['id' => 1, 'model' => 'F-350'],
                    'carnet_status'    => 'VERIFIED',
                    'carnet_file_path' => 'storage/documents/carnet.jpg',
                    'is_operable'      => true,
                ],
            ],
            'pending_requirements'   => [],
            'status_message'         => 'Operativo y validado.',
        ];

        $dto = DriverFleetStatusResponseDto::fromArray($payload);

        $this->assertSame(7, $dto->driverId);
        $this->assertSame('Pedro Conductor', $dto->driverName);
        $this->assertTrue($dto->canOperate);
        $this->assertCount(1, $dto->cranes);
        $this->assertInstanceOf(DriverFleetCraneStatusDto::class, $dto->cranes[0]);
        $this->assertSame('VERIFIED', $dto->cranes[0]->carnetStatus);
        $this->assertSame($payload, $dto->toArray());
    }

    public function testDriverLicenseResponseDtoFromArrayAndToArray(): void
    {
        $payload = [
            'id'             => 12,
            'document_type'  => 'driving_license',
            'file_path'      => 'storage/documents/lic.jpg',
            'status'         => 'PENDING',
            'extracted_data' => ['grade' => '5TO GRADO'],
            'message'        => 'Licencia subida para revisión.',
        ];

        $dto = DriverLicenseResponseDto::fromArray($payload);

        $this->assertSame(12, $dto->id);
        $this->assertSame('driving_license', $dto->documentType);
        $this->assertSame('PENDING', $dto->status);
        $this->assertSame($payload, $dto->toArray());
    }

    public function testDriverLocationResponseDtoFromArrayAndToArray(): void
    {
        $payload = [
            'status'      => 'success',
            'driver_id'   => 3,
            'crane_id'    => 44,
            'coordinates' => [
                'latitude'  => 10.4806,
                'longitude' => -66.9036,
            ],
            'speed_kmh'   => 45.5,
            'recorded_at' => '2026-10-23 20:00:00',
        ];

        $dto = DriverLocationResponseDto::fromArray($payload);

        $this->assertSame(3, $dto->driverId);
        $this->assertSame(44, $dto->craneId);
        $this->assertSame(10.4806, $dto->latitude);
        $this->assertSame(-66.9036, $dto->longitude);
        $this->assertSame(45.5, $dto->speedKmh);
        $this->assertSame('2026-10-23 20:00:00', $dto->recordedAt);
        $this->assertSame($payload, $dto->toArray());
    }

    public function testDriverShiftActionResponseDto(): void
    {
        // Caso start shift
        $startPayload = [
            'shift_id'     => 101,
            'driver_id'    => 5,
            'active_crane' => ['id' => '1', 'uuid' => 'u-1', 'plate_number' => 'ABC123D', 'brand' => 'Ford', 'model' => 'F350', 'color' => 'Blanco', 'year' => 2020],
            'is_on_duty'   => true,
            'started_at'   => '2026-10-23 08:00:00',
            'message'      => 'Turno iniciado con éxito.',
        ];

        $dto = DriverShiftActionResponseDto::fromArray($startPayload);
        $this->assertSame(101, $dto->shiftId);
        $this->assertSame(5, $dto->driverId);
        $this->assertTrue($dto->isOnDuty);
        $this->assertSame($startPayload, $dto->toArray());

        // Caso end shift
        $endPayload = [
            'shift_id'   => 101,
            'driver_id'  => 5,
            'is_on_duty' => false,
            'ended_at'   => '2026-10-23 18:00:00',
            'message'    => 'Turno finalizado con éxito.',
        ];

        $dtoEnd = DriverShiftActionResponseDto::fromArray($endPayload);
        $this->assertFalse($dtoEnd->isOnDuty);
        $this->assertSame('Turno finalizado con éxito.', $dtoEnd->message);
        $this->assertSame($endPayload, $dtoEnd->toArray());
    }

    public function testDriverShiftResponseDtoInactiveAndFromEntity(): void
    {
        $inactive = DriverShiftResponseDto::inactive();
        $this->assertFalse($inactive->isActive);
        $this->assertNull($inactive->shiftId);
        $this->assertNull($inactive->crane);

        $shift = new DriverShift(
            id: 200,
            driverId: 10,
            craneId: 20,
            startedAt: new DateTimeImmutable('2026-10-23 09:00:00'),
            endedAt: null,
            isActive: true,
            lastLatitude: 10.488,
            lastLongitude: -66.879,
            lastPingAt: new DateTimeImmutable('2026-10-23 09:15:00')
        );

        $vehicle = new Vehicle(
            id: 20,
            uuid: Uuid::generate(),
            userId: 10,
            plateNumber: new PlateNumber('AB123CD'),
            brandName: 'Mack',
            modelName: 'Granite',
            color: 'Amarillo',
            year: 2018
        );

        $activeDto = DriverShiftResponseDto::fromEntity($shift, $vehicle);
        $this->assertTrue($activeDto->isActive);
        $this->assertSame(200, $activeDto->shiftId);
        $this->assertSame(20, $activeDto->craneId);
        $this->assertSame('Mack', $activeDto->crane['brand']);
        $this->assertSame(10.488, $activeDto->lastLocation['latitude']);

        $array = $activeDto->toArray();
        $this->assertTrue($array['is_active']);
        $this->assertSame(200, $array['shift_id']);
    }
}
