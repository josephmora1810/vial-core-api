<?php

namespace App\Application\Payments\UseCases;

use App\Application\Commissions\UseCases\GenerateOrderCommission;
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
        private ?string $webhookSecret = null,
        private ?GenerateOrderCommission $generateOrderCommission = null
    ) {
        if (empty($this->webhookSecret) && !empty($_ENV['BANCARIBE_WEBHOOK_SECRET'])) {
            $this->webhookSecret = (string)$_ENV['BANCARIBE_WEBHOOK_SECRET'];
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param string|null $signatureHeader
     * @param string|null $rawPayloadJson
     * @return array<string, mixed>
     */
    public function execute(array $payload, ?string $signatureHeader = null, ?string $rawPayloadJson = null): array
    {
        // 1. Exigir obligatoriamente el secreto del webhook (OWASP API8:2023)
        if (empty($this->webhookSecret)) {
            throw new Exception('El secreto del webhook no está configurado en el servidor.', 500);
        }

        $rawBody = $rawPayloadJson ?? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $calculatedSignature = hash_hmac('sha256', $rawBody, $this->webhookSecret);
        $providedSignature = $signatureHeader ?? (string)($payload['signature'] ?? $payload['hash'] ?? $payload['webhook_secret'] ?? '');

        // Validar firma criptográfica HMAC-SHA256 o token secreto compartido
        $isValidHmac = hash_equals($calculatedSignature, $providedSignature);
        $isValidSecret = hash_equals($this->webhookSecret, $providedSignature);

        if (!$isValidHmac && !$isValidSecret) {
            throw new Exception('Firma no autorizada.', 401);
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

                // FR-14, FR-15: Generar comisión comercial para la vendedora si el contrato tiene seller_id
                if ($this->generateOrderCommission !== null) {
                    $this->generateOrderCommission->execute($order, $contract);
                }

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
