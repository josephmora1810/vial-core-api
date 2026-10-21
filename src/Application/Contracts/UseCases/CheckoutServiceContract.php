<?php

namespace App\Application\Contracts\UseCases;

use App\Application\Payments\DTOs\OrderResponseDto;
use App\Application\Contracts\DTOs\ServiceContractResponseDto;
use App\Domain\Payments\Entities\Order;
use App\Domain\Contracts\Entities\ServiceContract;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Payments\Enums\OrderableType;
use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Payments\Repositories\OrderRepositoryInterface;
use App\Domain\Contracts\Repositories\PlanRepositoryInterface;
use App\Domain\Contracts\Repositories\ServiceContractRepositoryInterface;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use App\Domain\Payments\Services\ExchangeRateServiceInterface;
use App\Domain\Shared\ValueObjects\Uuid;
use Exception;
use InvalidArgumentException;

class CheckoutServiceContract
{
    public function __construct(
        private PlanRepositoryInterface $planRepository,
        private VehicleRepositoryInterface $vehicleRepository,
        private ServiceContractRepositoryInterface $contractRepository,
        private OrderRepositoryInterface $orderRepository,
        private ExchangeRateServiceInterface $exchangeRateService,
        private string $commerceRif = 'J502098500',
        private string $commercePhone = '04267536320',
        private string $commerceName = 'UbikGo'
    ) {}

    public function execute(
        int $userId,
        string|int $planIdentifier,
        string|int|null $vehicleIdentifier = null,
        ?int $sellerId = null
    ): array {
        // 1. Resolver el Plan por UUID o ID
        $plan = is_numeric($planIdentifier)
            ? $this->planRepository->findById((int)$planIdentifier)
            : $this->planRepository->findByUuid((string)$planIdentifier);

        if ($plan === null || !$plan->isActive() || $plan->getId() === null) {
            throw new Exception('El plan seleccionado no existe o no se encuentra disponible.', 404);
        }

        // 2. Resolver el Vehículo (si fue suministrado) y validar propiedad del cliente ($userId)
        $vehicleId = null;
        $vehiclePlate = null;

        if ($vehicleIdentifier !== null && $vehicleIdentifier !== '') {
            if (is_numeric($vehicleIdentifier)) {
                $vehicle = $this->vehicleRepository->findById((int)$vehicleIdentifier);
            } else {
                try {
                    $vehicle = $this->vehicleRepository->findByUuid(Uuid::fromString((string)$vehicleIdentifier));
                } catch (InvalidArgumentException) {
                    $vehicle = null;
                }
            }

            if ($vehicle === null || $vehicle->getUserId() !== $userId || $vehicle->getId() === null) {
                throw new Exception('El vehículo seleccionado no existe o no pertenece al cliente indicado.', 404);
            }

            // Verificar si el vehículo ya posee un contrato activo vigente
            $existingActive = $this->contractRepository->findActiveByVehicleId($vehicle->getId());
            if ($existingActive !== null) {
                throw new Exception('Este vehículo ya cuenta con un contrato de servicio activo y vigente.', 409);
            }

            $vehicleId = $vehicle->getId();
            $vehiclePlate = $vehicle->getPlateNumber()->getValue();
        }

        // 3. Obtener tasa de cambio BCV actual y calcular monto exacto en Bolívares
        $exchangeRate = $this->exchangeRateService->getExchangeRate('USD', 'venta');
        $amountUsd = $plan->getPriceUsd();
        $amountVes = $plan->calculatePriceInVes($exchangeRate);

        // 4. Crear el Contrato de Servicio en estado PENDING_PAYMENT (registrando seller_id si lo vendió una asesora)
        $contract = new ServiceContract(
            clientId: $userId,
            planId: $plan->getId(),
            remainingServices: $plan->getMaxServices(),
            remainingKm: $plan->getMaxKm(),
            vehicleId: $vehicleId,
            status: ContractStatus::PendingPayment,
            sellerId: $sellerId
        );
        $contract = $this->contractRepository->save($contract);

        // 5. Crear la Orden Polimórfica en estado PENDING (1 orden por año de contrato)
        $order = new Order(
            userId: $userId,
            orderableType: OrderableType::ServiceContract,
            orderableId: (int)$contract->getId(),
            amountUsd: $amountUsd,
            exchangeRate: $exchangeRate,
            amountVes: $amountVes,
            status: OrderStatus::Pending
        );
        $order = $this->orderRepository->save($order);

        $contractDto = ServiceContractResponseDto::fromEntity($contract, $plan->getName(), $vehiclePlate)->toArray();
        $contractDto['pdf_download_url'] = '/api/v1/contracts/' . $contract->getUuid()->getValue() . '/pdf';

        return [
            'contract'             => $contractDto,
            'order'                => OrderResponseDto::fromEntity($order)->toArray(),
            'payment_instructions' => [
                'commerce_name'      => $this->commerceName,
                'commerce_rif'       => $this->commerceRif,
                'commerce_phone'     => $this->commercePhone,
                'commerce_bank_code' => '0114',
                'commerce_bank_name' => 'Bancaribe',
                'supported_methods'  => $sellerId !== null ? ['pago_movil'] : ['c2p', 'pago_movil'],
                'amount_ves'         => $amountVes->getAmount(),
                'amount_usd'         => $amountUsd->getAmount(),
                'exchange_rate'      => $exchangeRate,
            ],
        ];
    }
}
