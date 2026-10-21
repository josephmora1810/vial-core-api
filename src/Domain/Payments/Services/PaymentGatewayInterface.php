<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Entities\Order;
use App\Domain\Payments\ValueObjects\PaymentResult;

interface PaymentGatewayInterface
{
    public function processC2p(Order $order, array $paymentData): PaymentResult;

    public function verifyPagoMovil(
        string $reference,
        string $date,
        float $amount,
        string $identificadorPersona,
        ?string $telefono = null
    ): PaymentResult;
}
