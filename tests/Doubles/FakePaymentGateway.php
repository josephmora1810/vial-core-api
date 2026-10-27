<?php

namespace Tests\Doubles;

use App\Domain\Payments\Entities\Order;
use App\Domain\Payments\Services\PaymentGatewayInterface;
use App\Domain\Payments\ValueObjects\PaymentResult;

class FakePaymentGateway implements PaymentGatewayInterface
{
    private ?PaymentResult $nextResult = null;

    public function setNextResult(PaymentResult $result): void
    {
        $this->nextResult = $result;
    }

    public function processC2p(Order $_, array $paymentData): PaymentResult
    {
        if ($this->nextResult !== null) {
            return $this->nextResult;
        }

        if (in_array($paymentData['otp'] ?? '', ['000000', '00000000'], true)) {
            return new PaymentResult(
                success: false,
                message: 'Transacción C2P rechazada por clave dinámica inválida.',
                transactionId: null,
                requestPayload: $paymentData,
                responsePayload: ['code' => 400, 'status' => 'DECLINED']
            );
        }

        return new PaymentResult(
            success: true,
            message: 'Pago C2P aprobado exitosamente.',
            transactionId: 'C2P-98765432',
            requestPayload: $paymentData,
            responsePayload: ['status' => 'SUCCESS', 'secuencial' => 'C2P-98765432']
        );
    }

    public function verifyPagoMovil(
        string $reference,
        string $date,
        float $amount,
        string $identificadorPersona,
        ?string $telefono = null
    ): PaymentResult {
        if ($this->nextResult !== null) {
            return $this->nextResult;
        }

        return new PaymentResult(
            success: true,
            message: 'Pago Móvil verificado exitosamente en Operaciones Plus.',
            transactionId: $reference,
            requestPayload: [
                'referencia'           => $reference,
                'fecha'                => $date,
                'monto'                => $amount,
                'identificadorPersona' => $identificadorPersona,
                'telefono'             => $telefono ?? 'telefono no ingresado'
            ],
            responsePayload: ['status' => 'SUCCESS', 'secuencial' => $reference, 'monto' => $amount]
        );
    }
}
