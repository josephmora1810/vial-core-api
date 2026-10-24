<?php

namespace App\Infrastructure\Services\Bancaribe;

use App\Domain\Payments\Entities\Order;
use App\Domain\Payments\Services\{PaymentGatewayInterface, PaymentStrategyInterface};
use App\Domain\Payments\ValueObjects\PaymentResult;

class PagoMovilStrategy implements PaymentStrategyInterface
{
    public function __construct(private PaymentGatewayInterface $gateway) {}

    public function process(Order $order, array $data, float $expectedAmountVes): PaymentResult
    {
        $identificador = (string)($data['identificadorPersona'] ?? '');
        $telefono = !empty($data['telefono']) ? (string)$data['telefono'] : null;
        $reference = (string)($data['reference'] ?? '');
        $date = (string)($data['date'] ?? date('Y-m-d'));

        return $this->gateway->verifyPagoMovil(
            reference: $reference,
            date: $date,
            amount: $expectedAmountVes,
            identificadorPersona: $identificador,
            telefono: $telefono
        );
    }
}
