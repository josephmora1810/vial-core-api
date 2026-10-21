<?php

namespace Tests\Unit\Application\UseCases;

use App\Application\Contracts\UseCases\CheckoutServiceContract;
use App\Domain\Contracts\Entities\Plan;
use App\Domain\Vehicles\Entities\Vehicle;
use App\Domain\Vehicles\ValueObjects\EngineSerial;
use App\Domain\Vehicles\ValueObjects\Niv;
use App\Domain\Vehicles\ValueObjects\PlateNumber;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\FakeExchangeRateService;
use Tests\Doubles\InMemoryOrderRepository;
use Tests\Doubles\InMemoryPlanRepository;
use Tests\Doubles\InMemoryServiceContractRepository;
use Tests\Doubles\InMemoryVehicleRepository;

class CheckoutServiceContractTest extends TestCase
{
    private InMemoryPlanRepository $planRepo;
    private InMemoryVehicleRepository $vehicleRepo;
    private InMemoryServiceContractRepository $contractRepo;
    private InMemoryOrderRepository $orderRepo;
    private FakeExchangeRateService $exchangeService;
    private CheckoutServiceContract $useCase;

    protected function setUp(): void
    {
        $this->planRepo = new InMemoryPlanRepository();
        $this->vehicleRepo = new InMemoryVehicleRepository();
        $this->contractRepo = new InMemoryServiceContractRepository();
        $this->orderRepo = new InMemoryOrderRepository();
        $this->exchangeService = new FakeExchangeRateService(usdRate: 45.50);

        $this->useCase = new CheckoutServiceContract(
            $this->planRepo,
            $this->vehicleRepo,
            $this->contractRepo,
            $this->orderRepo,
            $this->exchangeService
        );
    }

    public function testCheckoutCreatesPendingContractAndOrderWithFrozenExchangeRate(): void
    {
        $plan = $this->planRepo->save(new Plan(
            name: 'Plan Básico Vial 2026',
            priceUsd: 35.00,
            durationMonths: 12,
            maxServices: 2,
            maxKm: 50.00
        ));

        $vehicle = Vehicle::create(
            userId: 10,
            plateNumber: new PlateNumber('AB123CD'),
            niv: new Niv('1HGCM82633A123456'),
            engineSerial: new EngineSerial('ENG123456'),
            year: 2022,
            color: 'Blanco'
        );
        $this->vehicleRepo->save($vehicle);

        $result = $this->useCase->execute(
            userId: 10,
            planIdentifier: $plan->getUuid()->getValue(),
            vehicleIdentifier: $vehicle->getUuid()->getValue()
        );

        $this->assertSame('PENDING_PAYMENT', $result['contract']['status']);
        $this->assertSame(2, $result['contract']['remaining_services']);
        $this->assertSame(50.0, $result['contract']['remaining_km']);
        $this->assertSame('AB123CD', $result['contract']['vehicle_plate']);

        $this->assertSame('PENDING', $result['order']['status']);
        $this->assertSame(35.0, $result['order']['amount_usd']);
        $this->assertSame(45.5, $result['order']['exchange_rate']);
        $this->assertSame(1592.5, $result['order']['amount_ves']);
        $this->assertSame('0114', $result['payment_instructions']['commerce_bank_code']);
    }

    public function testCheckoutRejectsVehicleBelongingToAnotherUser(): void
    {
        $plan = $this->planRepo->save(new Plan(name: 'Plan Oro', priceUsd: 100.00));
        $vehicle = Vehicle::create(
            userId: 99,
            plateNumber: new PlateNumber('XY999ZZ'),
            niv: new Niv('2HGCM82633A999999'),
            engineSerial: new EngineSerial('ENG999999'),
            year: 2020,
            color: 'Negro'
        );
        $this->vehicleRepo->save($vehicle);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);

        $this->useCase->execute(
            userId: 10,
            planIdentifier: $plan->getId(),
            vehicleIdentifier: $vehicle->getUuid()->getValue()
        );
    }
}
