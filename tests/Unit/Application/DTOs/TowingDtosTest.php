<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTOs;

use App\Application\Towing\DTOs\{
    CancellationReasonsResponseDto,
    PusherAuthResponseDto,
    PusherWebhookResponseDto,
    RatingResponseDto,
    SosHistoryResponseDto,
    SosQuoteResponseDto,
    SosRadarResponseDto,
    SosRequestResponseDto,
    TelemetryLoggedResponseDto,
    TowServiceResponseDto
};
use App\Domain\Shared\ValueObjects\{Coordinates, Uuid};
use App\Domain\Towing\Entities\{Rating, TowService};
use PHPUnit\Framework\TestCase;

final class TowingDtosTest extends TestCase
{
    public function testSosQuoteResponseDto(): void
    {
        $data = [
            'can_request_service' => true,
            'nearby_cranes_count' => 3,
            'search_radius_km'    => 25.0,
            'route' => [
                'distance_km'      => 12.5,
                'duration_minutes' => 22.0,
            ],
            'pricing' => [
                'is_covered_by_plan' => true,
                'amount_usd'         => 0.0,
                'amount_ves'         => 0.0,
            ],
            'warning_message'     => null,
        ];

        $dto = SosQuoteResponseDto::fromArray($data);

        $this->assertTrue($dto->can_request_service);
        $this->assertSame(3, $dto->nearby_cranes_count);
        $this->assertSame(25.0, $dto->search_radius_km);
        $this->assertNull($dto->warning_message);
        $this->assertSame(12.5, $dto->route['distance_km']);
        $this->assertSame($data, $dto->toArray());
    }

    public function testSosRequestResponseDto(): void
    {
        $dto = SosRequestResponseDto::fromArray([
            'service'          => ['id' => 'service-uuid-123', 'status' => 'PENDING'],
            'requires_payment' => false,
            'order'            => null,
            'route'            => ['distance_km' => 15.0],
        ]);

        $this->assertFalse($dto->requires_payment);
        $this->assertNull($dto->order);
        $this->assertSame(['distance_km' => 15.0], $dto->route);
        $this->assertIsArray($dto->toArray());
    }

    public function testSosRadarResponseDto(): void
    {
        $services = [
            ['id' => 's1', 'status' => 'PENDING'],
            ['id' => 's2', 'status' => 'PENDING'],
        ];

        $dto = SosRadarResponseDto::fromList($services);

        $this->assertCount(2, $dto->services);
        $this->assertSame(['services' => $services], $dto->toArray());
    }

    public function testSosHistoryResponseDto(): void
    {
        $data = [
            'items' => [
                ['id' => 'srv-1', 'status' => 'COMPLETED'],
            ],
            'pagination' => [
                'total'       => 1,
                'page'        => 1,
                'limit'       => 10,
                'total_pages' => 1,
            ],
        ];

        $dto = SosHistoryResponseDto::fromArray($data);

        $this->assertCount(1, $dto->items);
        $this->assertSame(1, $dto->pagination['total']);
        $this->assertSame($data, $dto->toArray());
    }

    public function testCancellationReasonsResponseDto(): void
    {
        $data = [
            'reasons' => [
                ['code' => 'ACCIDENT_RESOLVED', 'description' => 'El vehículo logró encender'],
            ],
        ];

        $dto = CancellationReasonsResponseDto::fromArray($data);

        $this->assertNotNull($dto->reasons);
        $this->assertSame($data, $dto->toArray());
    }

    public function testTelemetryLoggedResponseDto(): void
    {
        $data = [
            'status'      => 'logged',
            'coordinates' => ['latitude' => 10.4880, 'longitude' => -66.8791],
            'speed_kmh'   => 45.5,
            'recorded_at' => '2026-10-23 20:00:00',
        ];

        $dto = TelemetryLoggedResponseDto::fromArray($data);

        $this->assertSame('logged', $dto->status);
        $this->assertSame(45.5, $dto->speed_kmh);
        $this->assertSame($data, $dto->toArray());
    }

    public function testPusherAuthResponseDto(): void
    {
        $dto = PusherAuthResponseDto::fromArray([
            'auth'         => 'key:signature123',
            'channel_data' => '{"user_id":10}',
        ]);

        $this->assertSame('key:signature123', $dto->auth);
        $this->assertSame('{"user_id":10}', $dto->channel_data);
        $this->assertSame([
            'auth'         => 'key:signature123',
            'channel_data' => '{"user_id":10}',
        ], $dto->toArray());
    }

    public function testPusherWebhookResponseDto(): void
    {
        $events = [
            ['event' => 'channel_occupied', 'channel' => 'private-cranes-radar'],
        ];

        $dto = PusherWebhookResponseDto::fromArray([
            'processed' => 1,
            'events'    => $events,
        ]);

        $this->assertSame(1, $dto->processed);
        $this->assertSame($events, $dto->events);
        $this->assertSame([
            'processed' => 1,
            'events'    => $events,
        ], $dto->toArray());
    }

    public function testRatingResponseDto(): void
    {
        $rating = new Rating(
            id: 1,
            towServiceId: 10,
            userId: 5,
            ratedUserId: 8,
            rating: 5,
            comment: 'Excelente servicio, muy rápido.',
            createdAt: new \DateTimeImmutable('2026-10-23 21:00:00')
        );

        $dto = RatingResponseDto::fromEntity($rating, 'srv-uuid-999');

        $this->assertSame(5, $dto->rating);
        $this->assertSame('Excelente servicio, muy rápido.', $dto->comment);
        $this->assertSame('srv-uuid-999', $dto->service_id);
        $this->assertArrayHasKey('created_at', $dto->toArray());
    }

    public function testTowServiceResponseDto(): void
    {
        $service = TowService::create(
            clientId: 2,
            vehicleId: 3,
            contractId: null,
            origin: new Coordinates(10.4880, -66.8791),
            originAddress: 'Chacao',
            destination: new Coordinates(10.5000, -66.9167),
            destinationAddress: 'Caracas Centro',
            savedAddressId: null,
            failureDescription: 'Batería descargada',
            estimatedDistanceKm: 8.5
        );

        $dto = TowServiceResponseDto::fromEntity($service, 'AB123CD');

        $this->assertSame('PENDING', $dto->status);
        $this->assertSame('AB123CD', $dto->vehicle_plate);
        $this->assertSame(8.5, $dto->estimated_distance_km);
        $this->assertSame('Chacao', $dto->origin_address);
        $this->assertArrayHasKey('id', $dto->toArray());
    }
}
