<?php

namespace App\Application\Payments\UseCases;

use App\Application\Payments\DTOs\OrderResponseDto;
use App\Application\Contracts\DTOs\ServiceContractResponseDto;
use App\Domain\Payments\Entities\Payment;
use App\Domain\Payments\Enums\OrderableType;
use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Repositories\OrderRepositoryInterface;
use App\Domain\Payments\Repositories\PaymentRepositoryInterface;
use App\Domain\Contracts\Repositories\PlanRepositoryInterface;
use App\Domain\Contracts\Repositories\ServiceContractRepositoryInterface;
use DateTimeImmutable;
use Exception;

class HandleBancaribeWebhook
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private PaymentRepositoryInterface $paymentRepository,
        private ServiceContractRepositoryInterface $contractRepository,
        private PlanRepositoryInterface $planRepository,
        private ?string $webhookSecret = null
    ) {}

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function execute(array $payload, ?string $signatureHeader = null): array
    {
        // 1. Verificar firma del webhook si está configurada
        if (!empty($this->webhookSecret)) {
            $providedSecret = $signatureHeader ?? (string)($payload['hash'] ?? $payload['webhook_secret'] ?? '');
            if (!hash_equals($this->webhookSecret, $providedSecret)) {
                throw new Exception('Firma de webhook inválida.', 401);
            }
        }

        $orderIdentifier = (string)($payload['order_number'] ?? $payload['order_id'] ?? $payload['factura'] ?? '');
        $reference = trim((string)($payload['reference'] ?? $payload['referencia'] ?? $payload['secuencial'] ?? ''));
        $rawStatus = strtoupper(trim((string)($payload['status'] ?? $payload['ejecucion'] ?? '')));

        if ($orderIdentifier === '' || $reference === '') {
            throw new Exception('El payload del webhook debe incluir el identificador de la orden y la referencia bancaria.', 422);
        }

        $order = is_numeric($orderIdentifier)
            ? $this->orderRepository->findById((int)$orderIdentifier)
            : ($this->orderRepository->findByOrderNumber($orderIdentifier) ?? $this->orderRepository->findByUuid($orderIdentifier));

        if ($order === null || $order->getId() === null) {
            throw new Exception('Orden no encontrada para la notificación bancaria.', 404);
        }

        if ($order->isPaid()) {
            return [
                'order'   => OrderResponseDto::fromEntity($order)->toArray(),
                'message' => 'La orden ya había sido procesada anteriormente (idempotente).',
            ];
        }

        $isApproved = in_array($rawStatus, ['0', 'APPROVED', 'PAID', 'COMPLETED', 'SUCCESS', 'EXITO'], true);
        $method = !empty($payload['payment_method']) ? PaymentMethod::fromString((string)$payload['payment_method']) : PaymentMethod::PagoMovil;

        if (!$isApproved) {
            $failedPayment = new Payment(
                orderId: $order->getId(),
                paidByUserId: $order->getUserId(),
                amount: $order->getAmountVes()->getAmount(),
                paymentMethod: $method,
                status: PaymentStatus::Failed,
                transactionId: $reference,
                commerce: 'Bancaribe Webhook',
                requestPayload: null,
                responsePayload: $payload,
                errorMessage: (string)($payload['message'] ?? $payload['descripcion'] ?? 'Pago rechazado vía Webhook')
            );
            $this->paymentRepository->save($failedPayment);
            $order->markAsFailed();
            $this->orderRepository->save($order);

            return [
                'order'   => OrderResponseDto::fromEntity($order)->toArray(),
                'status'  => 'failed',
                'message' => 'Notificación de rechazo bancario registrada.',
            ];
        }

        if ($this->paymentRepository->existsCompletedByTransactionId($reference)) {
            throw new Exception('La referencia bancaria ya fue utilizada en otra transacción.', 409);
        }

        $payment = new Payment(
            orderId: $order->getId(),
            paidByUserId: $order->getUserId(),
            amount: $order->getAmountVes()->getAmount(),
            paymentMethod: $method,
            status: PaymentStatus::Completed,
            transactionId: $reference,
            commerce: 'Bancaribe Webhook',
            requestPayload: null,
            responsePayload: $payload,
            paidAt: new DateTimeImmutable()
        );
        $this->paymentRepository->save($payment);

        $order->markAsPaid($reference);
        $this->orderRepository->save($order);

        $activatedContract = null;
        if ($order->getOrderableType() === OrderableType::ServiceContract) {
            $contract = $this->contractRepository->findById($order->getOrderableId());
            if ($contract !== null) {
                $plan = $this->planRepository->findById($contract->getPlanId());
                $contract->activate(
                    durationMonths: $plan ? $plan->getDurationMonths() : 12,
                    activatedAt: null,
                    resetServices: $plan?->getMaxServices(),
                    resetKm: $plan?->getMaxKm()
                );
                $this->contractRepository->save($contract);
                $activatedContract = ServiceContractResponseDto::fromEntity($contract, $plan?->getName())->toArray();
            }
        }

        return [
            'order'    => OrderResponseDto::fromEntity($order)->toArray(),
            'contract' => $activatedContract,
            'status'   => 'paid',
            'message'  => 'Webhook procesado y contrato activado exitosamente.',
        ];
    }
}
