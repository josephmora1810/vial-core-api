<?php

namespace App\Application\Payments\UseCases;

use App\Application\Commissions\UseCases\GenerateOrderCommission;
use App\Application\Payments\DTOs\OrderResponseDto;
use App\Application\Contracts\DTOs\ServiceContractResponseDto;
use App\Application\Towing\DTOs\TowServiceResponseDto;
use App\Domain\Payments\Entities\Payment;
use App\Domain\Payments\Enums\OrderableType;
use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Repositories\OrderRepositoryInterface;
use App\Domain\Payments\Repositories\PaymentRepositoryInterface;
use App\Domain\Contracts\Repositories\PlanRepositoryInterface;
use App\Domain\Contracts\Repositories\ServiceContractRepositoryInterface;
use App\Domain\Payments\Services\ExchangeRateServiceInterface;
use App\Domain\Payments\Services\PaymentGatewayInterface;
use App\Domain\Shared\Services\BroadcasterInterface;
use App\Domain\Shared\Services\PushNotificationInterface;
use App\Domain\Towing\Enums\TowServiceStatus;
use App\Domain\Towing\Repositories\TowServiceRepositoryInterface;
use App\Infrastructure\Services\Bancaribe\C2pStrategy;
use App\Infrastructure\Services\Bancaribe\PagoMovilStrategy;
use DateTimeImmutable;
use Exception;

class ProcessPayment
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private PaymentRepositoryInterface $paymentRepository,
        private ServiceContractRepositoryInterface $contractRepository,
        private PlanRepositoryInterface $planRepository,
        private PaymentGatewayInterface $paymentGateway,
        private ?ExchangeRateServiceInterface $exchangeRateService = null,
        private ?GenerateOrderCommission $generateOrderCommission = null,
        private ?TowServiceRepositoryInterface $towServiceRepository = null,
        private ?BroadcasterInterface $broadcaster = null,
        private ?PushNotificationInterface $pushNotificationService = null
    ) {}

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function execute(
        int $userId,
        string|int $orderIdentifier,
        array $data,
        bool $isSeller = false,
        bool $isAdmin = false
    ): array {
        // 1. Buscar la Orden por UUID, Order Number o ID
        $order = null;
        if (is_numeric($orderIdentifier)) {
            $order = $this->orderRepository->findById((int)$orderIdentifier);
        } else {
            $order = $this->orderRepository->findByUuid((string)$orderIdentifier)
                ?? $this->orderRepository->findByOrderNumber((string)$orderIdentifier);
        }

        if ($order === null || $order->getId() === null) {
            throw new Exception('La orden de pago solicitada no existe.', 404);
        }

        // Verificar autorización: el titular de la orden, la vendedora asignada al contrato, o un administrador
        $contractForOrder = null;
        if ($order->getOrderableType() === OrderableType::ServiceContract) {
            $contractForOrder = $this->contractRepository->findById($order->getOrderableId());
        }

        $isOwner = ($order->getUserId() === $userId);
        $isAssignedSeller = $isSeller && ($contractForOrder !== null && ($contractForOrder->getSellerId() === $userId || $contractForOrder->getSellerId() === null));

        if (!$isOwner && !$isAssignedSeller && !$isAdmin) {
            throw new Exception('No tienes permiso para pagar una orden que pertenece a otro usuario.', 403);
        }

        // Regla de negocio: una orden solo puede tener un único pago exitoso (completed); los demás intentos previos pueden ser failed
        if ($order->isPaid()) {
            throw new Exception('Esta orden ya fue pagada y procesada anteriormente.', 409);
        }

        $existingPayments = $this->paymentRepository->findByOrderId($order->getId());
        foreach ($existingPayments as $existingPayment) {
            if ($existingPayment->getStatus() === PaymentStatus::Completed) {
                throw new Exception('Esta orden ya cuenta con un registro de pago exitoso.', 409);
            }
        }

        // Si el cliente o vendedor dejó esperando la orden y la tasa BCV del día cambió,
        // actualizamos la orden a la tasa BCV vigente del día del pago
        if ($this->exchangeRateService !== null) {
            $currentRate = $this->exchangeRateService->getExchangeRate('USD', 'venta');
            if ($order->refreshExchangeRate($currentRate)) {
                $this->orderRepository->save($order);
            }
        }

        // 2. Validar el método de pago
        $rawMethod = (string)($data['payment_method'] ?? '');
        if ($rawMethod === '') {
            throw new Exception('El campo payment_method es obligatorio (c2p o pago_movil).', 422);
        }
        $method = PaymentMethod::fromString($rawMethod);

        // Regla de negocio: Las vendedoras solo pueden registrar el pago por Consulta de Operaciones Plus (pago_movil),
        // ya que no pueden consultar claves OTP desde la cuenta bancaria del cliente para hacer C2P
        if (($isSeller && !$isOwner) || ($isSeller && $method === PaymentMethod::C2p)) {
            if ($method !== PaymentMethod::PagoMovil) {
                throw new Exception(
                    'Las vendedoras solo pueden conciliar pagos mediante Pago Móvil (Consulta de Operaciones Plus por referencia).',
                    422
                );
            }
        }

        $expectedAmountVes = $order->getAmountVes()->getAmount();

        // 3. Validaciones previas específicas según el método
        if ($method === PaymentMethod::PagoMovil) {
            $reference = trim((string)($data['reference'] ?? ''));
            $date = trim((string)($data['date'] ?? date('Y-m-d')));
            $identificador = trim((string)($data['identificadorPersona'] ?? ''));
            $reportedAmount = isset($data['amount']) ? (float)$data['amount'] : $expectedAmountVes;

            if ($reference === '' || $identificador === '') {
                throw new Exception('Para Pago Móvil debe suministrar reference e identificadorPersona (ej. V12345678).', 422);
            }

            // Anti-Replay: impedir reutilizar la misma referencia en dos órdenes distintas
            if ($this->paymentRepository->existsCompletedByTransactionId($reference)) {
                throw new Exception('Esta referencia bancaria ya fue utilizada en otro pago.', 409);
            }

            // Verificar que el monto reportado coincida con el total de la orden en VES
            if (abs($reportedAmount - $expectedAmountVes) > 0.50) {
                throw new Exception(sprintf(
                    'El monto reportado (%.2f VES) no coincide con el total de la orden (%.2f VES).',
                    $reportedAmount,
                    $expectedAmountVes
                ), 422);
            }

            $strategy = new PagoMovilStrategy($this->paymentGateway);
        } else {
            // Método C2P
            $bancoPagador = trim((string)($data['bancoPagador'] ?? ''));
            $telefonoPersona = trim((string)($data['telefonoPersona'] ?? ''));
            $otp = trim((string)($data['otp'] ?? ''));
            $docNumber = trim((string)($data['documentNumber'] ?? ''));

            if ($bancoPagador === '' || $telefonoPersona === '' || $otp === '' || $docNumber === '') {
                throw new Exception(
                    'Para pago C2P son obligatorios: bancoPagador (4 dígitos), documentType, documentNumber, telefonoPersona y otp.',
                    422
                );
            }

            $strategy = new C2pStrategy($this->paymentGateway);
        }

        // 4. Ejecutar consulta/cargo en Bancaribe a través de la estrategia
        $result = $strategy->process($order, $data, $expectedAmountVes);

        // 5. Si el banco declina o falla, registrar auditoría del intento fallido y notificar error (FR-30)
        if (!$result->success) {
            $failedPayment = new Payment(
                orderId: $order->getId(),
                paidByUserId: $userId,
                amount: $expectedAmountVes,
                paymentMethod: $method,
                status: PaymentStatus::Failed,
                transactionId: $result->transactionId,
                commerce: 'Bancaribe',
                requestPayload: $result->requestPayload,
                responsePayload: $result->responsePayload,
                errorMessage: substr($result->message, 0, 250)
            );
            $this->paymentRepository->save($failedPayment);

            throw new Exception('Transacción declinada por el banco: ' . $result->message, 422);
        }

        $txId = $result->transactionId ?: ($data['reference'] ?? ('BNK-' . bin2hex(random_bytes(4))));

        // Doble verificación anti-replay con el secuencial devuelto por Bancaribe
        if ($this->paymentRepository->existsCompletedByTransactionId((string)$txId)) {
            throw new Exception('Esta transacción bancaria ya fue registrada previamente.', 409);
        }

        // 6. Registrar el Pago Exitoso (único completed por orden)
        $completedPayment = new Payment(
            orderId: $order->getId(),
            paidByUserId: $userId,
            amount: $expectedAmountVes,
            paymentMethod: $method,
            status: PaymentStatus::Completed,
            transactionId: (string)$txId,
            commerce: 'Bancaribe',
            requestPayload: $result->requestPayload,
            responsePayload: $result->responsePayload,
            paidAt: new DateTimeImmutable()
        );
        $completedPayment = $this->paymentRepository->save($completedPayment);

        // 7. Marcar la Orden como PAID
        $order->markAsPaid((string)$txId);
        $this->orderRepository->save($order);

        // 8. Si la orden pertenece a un Contrato de Servicio, activarlo o renovarlo automáticamente (FR-12)
        // restaurando los servicios y kilómetros del plan por el nuevo año de vigencia
        $activatedContractDto = null;
        if ($order->getOrderableType() === OrderableType::ServiceContract) {
            $contract = $contractForOrder ?? $this->contractRepository->findById($order->getOrderableId());
            if ($contract !== null) {
                $plan = $this->planRepository->findById($contract->getPlanId());
                $durationMonths = $plan ? $plan->getDurationMonths() : 12;
                $contract->activate(
                    durationMonths: $durationMonths,
                    activatedAt: null,
                    resetServices: $plan?->getMaxServices(),
                    resetKm: $plan?->getMaxKm()
                );
                $this->contractRepository->save($contract);

                // FR-14, FR-15: Generar comisión comercial para la vendedora si el contrato tiene seller_id
                if ($this->generateOrderCommission !== null) {
                    $this->generateOrderCommission->execute($order, $contract);
                }

                $activatedContractDto = ServiceContractResponseDto::fromEntity(
                    $contract,
                    $plan?->getName()
                )->toArray();
                $activatedContractDto['pdf_download_url'] = '/api/v1/contracts/' . $contract->getUuid()->getValue() . '/pdf';
            }
        }

        // 9. Si la orden pertenece a un Servicio de Asistencia Vial (TowService), difundir al radar de grúas
        // ahora que el pago del excedente / express fue confirmado (OWASP Payment Bypass Protection)
        if ($order->getOrderableType() === OrderableType::TowService && $this->towServiceRepository !== null) {
            $towService = $this->towServiceRepository->findById($order->getOrderableId());
            if ($towService !== null && $towService->getStatus() === TowServiceStatus::Pending) {
                $serviceDto = TowServiceResponseDto::fromEntity(
                    service: $towService,
                    extraOrderUuid: $order->getUuid()->getValue()
                )->toArray();

                $this->broadcaster?->broadcast(
                    channels: 'private-cranes-radar',
                    event: 'new-sos-request',
                    data: $serviceDto
                );

                $this->pushNotificationService?->publishToInterests(
                    interests: ['cranes-radar'],
                    title: '🚨 ¡NUEVA SOLICITUD DE AUXILIO VIAL!',
                    body: "Avería reportada en {$towService->getOriginAddress()}. Toca para ver la solicitud.",
                    data: [
                        'service_uuid' => $towService->getUuid()->getValue(),
                    ]
                );
            }
        }

        return [
            'payment' => [
                'uuid'           => $completedPayment->getUuid()->getValue(),
                'transaction_id' => $completedPayment->getTransactionId(),
                'payment_method' => $completedPayment->getPaymentMethod()->getValue(),
                'amount_ves'     => $completedPayment->getAmount()->getAmount(),
                'status'         => $completedPayment->getStatus()->getValue(),
                'paid_at'        => $completedPayment->getPaidAt()?->format('Y-m-d H:i:s'),
                'bank_message'   => $result->message,
            ],
            'order'    => OrderResponseDto::fromEntity($order)->toArray(),
            'contract' => $activatedContractDto,
        ];
    }
}
