<?php

namespace App\Infrastructure\Services\Bancaribe;

use App\Domain\Payments\Entities\Order;
use App\Domain\Payments\Services\PaymentGatewayInterface;
use App\Domain\Payments\Services\PaymentStrategyInterface;
use App\Domain\Payments\ValueObjects\PaymentResult;

class C2pStrategy implements PaymentStrategyInterface
{
    public function __construct(private PaymentGatewayInterface $gateway) {}

    public function process(Order $order, array $data, float $expectedAmountVes): PaymentResult
    {
        $c2pData = [
            'bancoPagador'    => $data['bancoPagador'] ?? null,
            'documentType'    => $data['documentType'] ?? null,
            'documentNumber'  => $data['documentNumber'] ?? null,
            'telefonoPersona' => $data['telefonoPersona'] ?? null,
            'otp'             => $data['otp'] ?? null,
            'ip_address'      => $data['ip_address'] ?? '127.0.0.1',
        ];

        return $this->gateway->processC2p($order, $c2pData);
    }
}
