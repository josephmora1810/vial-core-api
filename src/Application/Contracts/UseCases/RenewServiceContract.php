<?php

namespace App\Application\Contracts\UseCases;

use App\Application\Contracts\DTOs\ServiceContractResponseDto;
use App\Application\Payments\DTOs\OrderResponseDto;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Contracts\Repositories\PlanRepositoryInterface;
use App\Domain\Contracts\Repositories\ServiceContractRepositoryInterface;
use App\Domain\Payments\Entities\Order;
use App\Domain\Payments\Enums\OrderableType;
use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Payments\Repositories\OrderRepositoryInterface;
use App\Domain\Payments\Services\ExchangeRateServiceInterface;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use Exception;

class RenewServiceContract
{
    public function __construct(
        private ServiceContractRepositoryInterface $contractRepository,
        private PlanRepositoryInterface $planRepository,
        private VehicleRepositoryInterface $vehicleRepository,
        private OrderRepositoryInterface $orderRepository,
        private ExchangeRateServiceInterface $exchangeRateService,
        private string $commerceRif = 'J502098500',
        private string $commercePhone = '04267536320',
        private string $commerceName = 'UbikGo'
    ) {}

    /**
     * Genera una nueva Orden de Pago anual para renovar un Contrato de Servicio existente
     * (vencido o próximo a vencer). Al pagarse la orden, el contrato extiende su vigencia
     * por un año más y restaura los kilómetros y cantidad de servicios del plan.
     *
     * @return array<string, mixed>
     */
    public function execute(
        string|int $contractIdentifier,
        int $actorUserId,
        bool $isSeller = false,
        bool $isAdmin = false
    ): array {
        $identifier = trim((string)$contractIdentifier);

        $contract = ctype_digit($identifier)
            ? $this->contractRepository->findById((int)$identifier)
            : $this->contractRepository->findByUuid($identifier);

        if ($contract === null || $contract->getId() === null) {
            throw new Exception('El contrato que intenta renovar no existe.', 404);
        }

        if ($contract->getClientId() !== $actorUserId && !$isSeller && !$isAdmin) {
            throw new Exception('No tienes permiso para renovar este contrato.', 403);
        }

        if ($contract->getStatus() === ContractStatus::PendingPayment) {
            throw new Exception('Este contrato aún se encuentra pendiente de su primer pago; pague su orden inicial.', 409);
        }

        // Si el contrato estaba ACTIVE pero ya venció su fecha, pasarlo a EXPIRED
        if ($contract->getStatus() === ContractStatus::Active && !$contract->isActive()) {
            $contract->expire();
        }

        // Si la renovación la gestiona una vendedora, asociar su seller_id al contrato
        if ($isSeller) {
            $contract->setSellerId($actorUserId);
        }
        $this->contractRepository->save($contract);

        $plan = $this->planRepository->findById($contract->getPlanId());
        if ($plan === null || $plan->getId() === null) {
            throw new Exception('El plan asociado al contrato no existe.', 404);
        }

        $vehiclePlate = null;
        if ($contract->getVehicleId() !== null) {
            $vehicle = $this->vehicleRepository->findById($contract->getVehicleId());
            $vehiclePlate = $vehicle?->getPlateNumber()->getValue();
        }

        $exchangeRate = $this->exchangeRateService->getExchangeRate('USD', 'venta');
        $amountUsd = $plan->getPriceUsd();
        $amountVes = $plan->calculatePriceInVes($exchangeRate);

        // Verificar si ya existe una orden PENDING sin pagar para este contrato (evitar duplicar órdenes pendientes)
        $existingOrders = $this->orderRepository->findByOrderable(
            OrderableType::ServiceContract->getValue(),
            $contract->getId()
        );

        $renewalOrder = null;
        foreach ($existingOrders as $existingOrder) {
            if ($existingOrder->getStatus() === OrderStatus::Pending) {
                $existingOrder->refreshExchangeRate($exchangeRate);
                $renewalOrder = $this->orderRepository->save($existingOrder);
                break;
            }
        }

        // Regla de negocio: 1 orden por cada año de contrato (nueva orden al renovar)
        if ($renewalOrder === null) {
            $renewalOrder = new Order(
                userId: $contract->getClientId(),
                orderableType: OrderableType::ServiceContract,
                orderableId: $contract->getId(),
                amountUsd: $amountUsd,
                exchangeRate: $exchangeRate,
                amountVes: $amountVes,
                status: OrderStatus::Pending
            );
            $renewalOrder = $this->orderRepository->save($renewalOrder);
        }

        $contractDto = ServiceContractResponseDto::fromEntity($contract, $plan->getName(), $vehiclePlate)->toArray();
        $contractDto['pdf_download_url'] = '/api/v1/contracts/' . $contract->getUuid()->getValue() . '/pdf';

        return [
            'contract'             => $contractDto,
            'order'                => OrderResponseDto::fromEntity($renewalOrder)->toArray(),
            'is_renewal'           => true,
            'payment_instructions' => [
                'commerce_name'      => $this->commerceName,
                'commerce_rif'       => $this->commerceRif,
                'commerce_phone'     => $this->commercePhone,
                'commerce_bank_code' => '0114',
                'commerce_bank_name' => 'Bancaribe',
                'supported_methods'  => $isSeller ? ['pago_movil'] : ['c2p', 'pago_movil'],
                'amount_ves'         => $renewalOrder->getAmountVes()->getAmount(),
                'amount_usd'         => $renewalOrder->getAmountUsd()->getAmount(),
                'exchange_rate'      => $renewalOrder->getExchangeRate(),
            ],
        ];
    }
}
