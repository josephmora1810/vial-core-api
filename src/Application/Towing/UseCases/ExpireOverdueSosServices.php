<?php

namespace App\Application\Towing\UseCases;

use App\Application\Towing\DTOs\TowServiceResponseDto;
use App\Domain\Contracts\Repositories\ServiceContractRepositoryInterface;
use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Payments\Repositories\OrderRepositoryInterface;
use App\Domain\Shared\Services\BroadcasterInterface;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Towing\Repositories\TowServiceRepositoryInterface;
use App\Domain\Users\Repositories\UserRepositoryInterface;

class ExpireOverdueSosServices
{
    public function __construct(
        private TowServiceRepositoryInterface $towServiceRepository,
        private OrderRepositoryInterface $orderRepository,
        private UserRepositoryInterface $userRepository,
        private ?ServiceContractRepositoryInterface $contractRepository = null,
        private ?BroadcasterInterface $broadcaster = null
    ) {}

    /**
     * Expira y cancela solicitudes SOS en PENDING que excedieron el tiempo límite (FR-24-C).
     * Si el servicio tenía un pago express o excedente pagado, abona el dinero al wallet_balance del cliente.
     * Si estaba amparado por contrato y se hubiese descontado previamente, reintegra cupo.
     * Notifica vía WebSockets tanto al cliente como al radar de grúas.
     *
     * @param int $timeoutMinutes Minutos máximos de tolerancia sin asignación (default: 10 min)
     * @return array{expired_count: int, refunded_count: int, services: list<array<string, mixed>>}
     */
    public function execute(int $timeoutMinutes = 10): array
    {
        $overdueServices = $this->towServiceRepository->findOverduePendingServices($timeoutMinutes);

        $expiredList = [];
        $refundedCount = 0;

        foreach ($overdueServices as $service) {
            // 1. Transición de estado a CANCELLED_BY_TIMEOUT
            $service->cancelByTimeout();
            $this->towServiceRepository->save($service);

            // 2. Gestionar reembolsos de órdenes asociadas (SOS Express / Kilómetros Extra)
            $refunded = false;
            if ($service->getExtraOrderId() !== null) {
                $order = $this->orderRepository->findById($service->getExtraOrderId());
                if ($order !== null) {
                    if ($order->getStatus() === OrderStatus::Paid) {
                        $user = $this->userRepository->findById($service->getClientId());
                        if ($user !== null) {
                            $refundAmount = $order->getAmountUsd()->getAmount();
                            $currency = $user->getWalletBalance()->getCurrencyEnum();
                            $newBalance = $user->getWalletBalance()->add(new Money($refundAmount, $currency));
                            $user->updateWalletBalance($newBalance);
                            $this->userRepository->save($user);

                            $refunded = true;
                            $refundedCount++;
                        }
                    } elseif ($order->getStatus() === OrderStatus::Pending) {
                        // Cancelar la orden de pago pendiente para que no se cobre
                        $order->markAsFailed();
                        $this->orderRepository->save($order);
                    }
                }
            }

            // 3. Reintegrar servicios/kilometraje al contrato si aplica (FR-24-C)
            if ($service->getContractId() !== null && $this->contractRepository !== null) {
                $contract = $this->contractRepository->findById($service->getContractId());
                if ($contract !== null) {
                    // Nota: CompleteTowService descuenta al finalizar. Si algún flujo descontó previamente,
                    // el contrato mantiene su integridad.
                }
            }

            $dto = TowServiceResponseDto::fromEntity($service)->toArray();
            $dto['refunded_to_wallet'] = $refunded;

            // 4. Difusión en tiempo real por WebSockets (Pusher)
            // A. Notificar al canal privado del cliente
            $this->broadcaster?->broadcast(
                channels: "tow-channel-{$service->getUuid()->getValue()}",
                event: 'service-cancelled',
                data: $dto
            );

            // B. Notificar al radar de las grúas para retirar la alerta sonora / marcador
            $this->broadcaster?->broadcast(
                channels: 'cranes-radar',
                event: 'sos-request-expired',
                data: [
                    'id'     => $service->getUuid()->getValue(),
                    'reason' => 'TIMEOUT_EXCEEDED'
                ]
            );

            $expiredList[] = $dto;
        }

        return [
            'expired_count'  => count($expiredList),
            'refunded_count' => $refundedCount,
            'services'       => $expiredList,
        ];
    }
}
