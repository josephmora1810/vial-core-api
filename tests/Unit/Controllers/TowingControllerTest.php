<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Application\Towing\DTOs\{
    RatingResponseDto,
    SosHistoryResponseDto,
    SosQuoteResponseDto,
    SosRadarResponseDto,
    SosRequestResponseDto,
    TelemetryLoggedResponseDto,
    TowServiceResponseDto
};
use App\Application\Towing\UseCases\{
    AcceptSosService,
    ArriveTowService,
    CancelTowService,
    CompleteTowService,
    FindNearbyPendingServices,
    GetActiveTowService,
    GetTowService,
    ListTowServiceHistory,
    QuoteSosAssistance,
    RateTowService,
    RequestSosAssistance,
    UpdateCraneTelemetry
};
use App\Controllers\TowingController;
use App\Core\{Request, Response};
use App\Domain\Auth\{Entities\Role, ValueObjects\Password};
use App\Domain\Shared\ValueObjects\{Coordinates, Money, Uuid};
use App\Domain\Users\Entities\User;
use App\Domain\Users\ValueObjects\{
    BirthDate,
    Email,
    Names,
    NationalId,
    Phone,
    Surnames
};
use DateTimeImmutable;
use DomainException;
use Exception;
use PHPUnit\Framework\TestCase;

final class TowingControllerTest extends TestCase
{
    private RequestSosAssistance $requestSosAssistance;
    private AcceptSosService $acceptSosService;
    private UpdateCraneTelemetry $updateCraneTelemetry;
    private ArriveTowService $arriveTowService;
    private CompleteTowService $completeTowService;
    private CancelTowService $cancelTowService;
    private RateTowService $rateTowService;
    private GetTowService $getTowService;
    private FindNearbyPendingServices $findNearbyPendingServices;
    private QuoteSosAssistance $quoteSosAssistance;
    private GetActiveTowService $getActiveTowService;
    private ListTowServiceHistory $listTowServiceHistory;
    private TowingController $controller;

    protected function setUp(): void
    {
        Response::$shouldExit = false;
        $_POST = [];
        $_GET = [];

        $this->requestSosAssistance = $this->createStub(RequestSosAssistance::class);
        $this->acceptSosService = $this->createStub(AcceptSosService::class);
        $this->updateCraneTelemetry = $this->createStub(UpdateCraneTelemetry::class);
        $this->arriveTowService = $this->createStub(ArriveTowService::class);
        $this->completeTowService = $this->createStub(CompleteTowService::class);
        $this->cancelTowService = $this->createStub(CancelTowService::class);
        $this->rateTowService = $this->createStub(RateTowService::class);
        $this->getTowService = $this->createStub(GetTowService::class);
        $this->findNearbyPendingServices = $this->createStub(FindNearbyPendingServices::class);
        $this->quoteSosAssistance = $this->createStub(QuoteSosAssistance::class);
        $this->getActiveTowService = $this->createStub(GetActiveTowService::class);
        $this->listTowServiceHistory = $this->createStub(ListTowServiceHistory::class);

        $this->controller = new TowingController(
            requestSosAssistance: $this->requestSosAssistance,
            acceptSosService: $this->acceptSosService,
            updateCraneTelemetry: $this->updateCraneTelemetry,
            arriveTowService: $this->arriveTowService,
            completeTowService: $this->completeTowService,
            cancelTowService: $this->cancelTowService,
            rateTowService: $this->rateTowService,
            getTowService: $this->getTowService,
            findNearbyPendingServices: $this->findNearbyPendingServices,
            quoteSosAssistance: $this->quoteSosAssistance,
            getActiveTowService: $this->getActiveTowService,
            listTowServiceHistory: $this->listTowServiceHistory
        );
    }

    protected function tearDown(): void
    {
        Response::$shouldExit = true;
        $_POST = [];
        $_GET = [];
    }

    private function createUser(int $id = 1, string $role = 'client'): User
    {
        return new User(
            id: $id,
            uuid: Uuid::generate(),
            names: new Names('Carlos'),
            surnames: new Surnames('Perez'),
            email: new Email('carlos@example.com'),
            password: Password::fromPlainText('SecurePass123!'),
            phone: new Phone('+584121234567'),
            walletBalance: new Money(0, 'VES'),
            identificationCard: new NationalId('V-12345678'),
            birthDate: new BirthDate('1990-01-01'),
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
            consentAt: new DateTimeImmutable(),
            deletedAt: null,
            isActive: true,
            roles: [new Role($role === 'driver' ? 2 : 1, $role)]
        );
    }

    private function capture(callable $callback): array
    {
        ob_start();
        try {
            $callback();
        } finally {
            $output = (string)ob_get_clean();
        }
        return json_decode($output, true) ?: [];
    }

    public function testQuoteUnauthorized(): void
    {
        $res = $this->capture(fn() => $this->controller->quote(new Request()));
        $this->assertSame(401, $res['code']);
    }

    public function testQuoteValidationErrors(): void
    {
        $request = new Request();
        $request->setUser($this->createUser());
        $_POST = [];

        $res = $this->capture(fn() => $this->controller->quote($request));
        $this->assertSame(422, $res['code']);
        $this->assertArrayHasKey('vehicle_id', $res['data']);
    }

    public function testQuoteSuccess(): void
    {
        $request = new Request();
        $request->setUser($this->createUser());
        $_POST = [
            'vehicle_id' => '1',
            'origin_lat' => 10.4880,
            'origin_lng' => -66.8791,
            'dest_lat'   => 10.5000,
            'dest_lng'   => -66.9167,
        ];

        $this->quoteSosAssistance->method('execute')->willReturn([
            'can_request_service' => true,
            'nearby_cranes_count' => 2,
            'search_radius_km'    => 25.0,
            'route'               => ['distance_km' => 10.0, 'duration_minutes' => 18.0],
            'pricing'             => ['amount_usd' => 35.0, 'amount_ves' => 1500.0],
            'warning_message'     => null,
        ]);

        $res = $this->capture(fn() => $this->controller->quote($request));
        $this->assertSame(200, $res['code']);
        $this->assertTrue($res['data']['can_request_service']);
    }

    public function testRequestSosValidationErrors(): void
    {
        $request = new Request();
        $request->setUser($this->createUser());
        $_POST = [];

        $res = $this->capture(fn() => $this->controller->requestSos($request));
        $this->assertSame(422, $res['code']);
    }

    public function testRequestSosSuccess(): void
    {
        $request = new Request();
        $request->setUser($this->createUser());
        $_POST = [
            'vehicle_id'          => '1',
            'origin_lat'          => 10.4880,
            'origin_lng'          => -66.8791,
            'origin_address'      => 'Chacao, Caracas',
            'dest_lat'            => 10.5000,
            'dest_lng'            => -66.9167,
            'dest_address'        => 'Plaza Venezuela',
            'failure_description' => 'Falla de encendido y alternador',
        ];

        $this->requestSosAssistance->method('execute')->willReturn([
            'service'          => ['id' => 'srv-123', 'status' => 'PENDING'],
            'requires_payment' => false,
            'order'            => null,
            'route'            => ['distance_km' => 8.5],
        ]);

        $res = $this->capture(fn() => $this->controller->requestSos($request));
        $this->assertSame(201, $res['code']);
        $this->assertSame('srv-123', $res['data']['service']['id']);
    }

    public function testRadarMissingCoordinates(): void
    {
        $res = $this->capture(fn() => $this->controller->radar(new Request()));
        $this->assertSame(422, $res['code']);
    }

    public function testRadarSuccess(): void
    {
        $_POST = ['lat' => 10.4880, 'lng' => -66.8791];
        $this->findNearbyPendingServices->method('execute')->willReturn([
            ['id' => 'srv-1', 'status' => 'PENDING'],
        ]);

        $res = $this->capture(fn() => $this->controller->radar(new Request()));
        $this->assertSame(200, $res['code']);
        $this->assertCount(1, $res['data']['services']);
    }

    public function testHistoryUnauthorized(): void
    {
        $res = $this->capture(fn() => $this->controller->history(new Request()));
        $this->assertSame(401, $res['code']);
    }

    public function testHistorySuccess(): void
    {
        $request = new Request();
        $request->setUser($this->createUser());

        $this->listTowServiceHistory->method('execute')->willReturn([
            'items'      => [['id' => 'srv-hist-1', 'status' => 'COMPLETED']],
            'pagination' => ['total' => 1, 'page' => 1, 'limit' => 10, 'total_pages' => 1],
        ]);

        $res = $this->capture(fn() => $this->controller->history($request));
        $this->assertSame(200, $res['code']);
        $this->assertCount(1, $res['data']['items']);
    }

    public function testActiveWhenNoServiceReturnsNull(): void
    {
        $request = new Request();
        $request->setUser($this->createUser());

        $this->getActiveTowService->method('execute')->willReturn(null);

        $res = $this->capture(fn() => $this->controller->active($request));
        $this->assertSame(200, $res['code']);
        $this->assertNull($res['data']);
    }

    public function testActiveWhenServiceActive(): void
    {
        $request = new Request();
        $request->setUser($this->createUser());

        $this->getActiveTowService->method('execute')->willReturn([
            'id'     => 'srv-active-1',
            'status' => 'IN_ROUTE',
        ]);

        $res = $this->capture(fn() => $this->controller->active($request));
        $this->assertSame(200, $res['code']);
        $this->assertSame('srv-active-1', $res['data']['id']);
    }

    public function testShowInvalidUuidReturns400(): void
    {
        $res = $this->capture(fn() => $this->controller->show(new Request(), '12345'));
        $this->assertSame(400, $res['code']);
    }

    public function testShowSuccess(): void
    {
        $this->getTowService->method('execute')->willReturn([
            'id'     => '550e8400-e29b-41d4-a716-446655440000',
            'status' => 'COMPLETED',
        ]);

        $res = $this->capture(fn() => $this->controller->show(new Request(), '550e8400-e29b-41d4-a716-446655440000'));
        $this->assertSame(200, $res['code']);
        $this->assertSame('COMPLETED', $res['data']['status']);
    }

    public function testAcceptUnauthorized(): void
    {
        $res = $this->capture(fn() => $this->controller->accept(new Request(), 'uuid-1'));
        $this->assertSame(401, $res['code']);
    }

    public function testAcceptConflictReturns409(): void
    {
        $request = new Request();
        $request->setUser($this->createUser(2, 'driver'));

        $this->acceptSosService->method('execute')
            ->willThrowException(new DomainException('El servicio ya fue tomado', 409));

        $res = $this->capture(fn() => $this->controller->accept($request, 'uuid-1'));
        $this->assertSame(409, $res['code']);
    }

    public function testAcceptSuccess(): void
    {
        $request = new Request();
        $request->setUser($this->createUser(2, 'driver'));

        $this->acceptSosService->method('execute')->willReturn([
            'id'     => 'uuid-1',
            'status' => 'IN_ROUTE',
        ]);

        $res = $this->capture(fn() => $this->controller->accept($request, 'uuid-1'));
        $this->assertSame(200, $res['code']);
        $this->assertSame('IN_ROUTE', $res['data']['status']);
    }

    public function testTelemetryMissingCoordinatesReturns422(): void
    {
        $request = new Request();
        $request->setUser($this->createUser(2, 'driver'));
        $_POST = [];

        $res = $this->capture(fn() => $this->controller->telemetry($request, 'uuid-1'));
        $this->assertSame(422, $res['code']);
    }

    public function testTelemetrySuccess(): void
    {
        $request = new Request();
        $request->setUser($this->createUser(2, 'driver'));
        $_POST = ['lat' => 10.4880, 'lng' => -66.8791, 'speed_kmh' => 45.0];

        $this->updateCraneTelemetry->method('execute')->willReturn([
            'status'      => 'logged',
            'coordinates' => ['latitude' => 10.4880, 'longitude' => -66.8791],
            'speed_kmh'   => 45.0,
            'recorded_at' => '2026-10-23 20:00:00',
        ]);

        $res = $this->capture(fn() => $this->controller->telemetry($request, 'uuid-1'));
        $this->assertSame(200, $res['code']);
        $this->assertSame('logged', $res['data']['status']);
    }

    public function testArriveSuccess(): void
    {
        $request = new Request();
        $request->setUser($this->createUser(2, 'driver'));

        $this->arriveTowService->method('execute')->willReturn([
            'id'     => 'uuid-1',
            'status' => 'IN_PROGRESS',
        ]);

        $res = $this->capture(fn() => $this->controller->arrive($request, 'uuid-1'));
        $this->assertSame(200, $res['code']);
        $this->assertSame('IN_PROGRESS', $res['data']['status']);
    }

    public function testCompleteSuccess(): void
    {
        $request = new Request();
        $request->setUser($this->createUser(2, 'driver'));

        $this->completeTowService->method('execute')->willReturn([
            'id'     => 'uuid-1',
            'status' => 'COMPLETED',
        ]);

        $res = $this->capture(fn() => $this->controller->complete($request, 'uuid-1'));
        $this->assertSame(200, $res['code']);
        $this->assertSame('COMPLETED', $res['data']['status']);
    }

    public function testCancellationReasons(): void
    {
        $_GET = ['role' => 'client'];
        $res = $this->capture(fn() => $this->controller->cancellationReasons(new Request()));
        $this->assertSame(200, $res['code']);
        $this->assertArrayHasKey('reasons', $res['data']);
    }

    public function testCancelMissingReasonReturns422(): void
    {
        $request = new Request();
        $request->setUser($this->createUser());
        $_POST = [];

        $res = $this->capture(fn() => $this->controller->cancel($request, 'uuid-1'));
        $this->assertSame(422, $res['code']);
    }

    public function testCancelSuccess(): void
    {
        $request = new Request();
        $request->setUser($this->createUser());
        $_POST = ['reason' => 'El vehículo encendió'];

        $this->cancelTowService->method('execute')->willReturn([
            'id'     => 'uuid-1',
            'status' => 'CANCELLED_BY_CLIENT',
        ]);

        $res = $this->capture(fn() => $this->controller->cancel($request, 'uuid-1'));
        $this->assertSame(200, $res['code']);
        $this->assertSame('CANCELLED_BY_CLIENT', $res['data']['status']);
    }

    public function testRateInvalidStarsReturns422(): void
    {
        $request = new Request();
        $request->setUser($this->createUser());
        $_POST = ['rating' => 0];

        $res = $this->capture(fn() => $this->controller->rate($request, 'uuid-1'));
        $this->assertSame(422, $res['code']);
    }

    public function testRateSuccess(): void
    {
        $request = new Request();
        $request->setUser($this->createUser());
        $_POST = ['rating' => 5, 'comment' => 'Servicio impecable'];

        $this->rateTowService->method('execute')->willReturn([
            'rating'     => 5,
            'comment'    => 'Servicio impecable',
            'created_at' => '2026-10-23 20:30:00',
            'service_id' => 'uuid-1',
        ]);

        $res = $this->capture(fn() => $this->controller->rate($request, 'uuid-1'));
        $this->assertSame(201, $res['code']);
        $this->assertSame(5, $res['data']['rating']);
    }
}
