<?php

declare(strict_types=1);

namespace App\Domain\Payments\Strategies;

use App\Domain\Payments\Entities\Order;
use App\Domain\Payments\Services\{PaymentGatewayInterface, PaymentStrategyInterface};
use App\Domain\Payments\ValueObjects\{BankCode, OtpCode, PaymentResult};
use App\Domain\Users\ValueObjects\Phone;
use InvalidArgumentException;

class C2pStrategy implements PaymentStrategyInterface
{
    public function __construct(protected PaymentGatewayInterface $gateway) {}

    public function process(Order $order, array $data, float $expectedAmountVes): PaymentResult
    {
        $bancoPagador = isset($data['bancoPagador']) && trim((string)$data['bancoPagador']) !== ''
            ? (string)BankCode::fromString((string)$data['bancoPagador'])
            : null;

        $otp = isset($data['otp']) && trim((string)$data['otp']) !== ''
            ? (string)OtpCode::fromString((string)$data['otp'])
            : null;

        $telefonoPersona = null;
        if (isset($data['telefonoPersona']) && trim((string)$data['telefonoPersona']) !== '') {
            try {
                $telefonoPersona = Phone::fromString((string)$data['telefonoPersona'])->toNationalFormat();
            } catch (InvalidArgumentException) {
                $telefonoPersona = (string)$data['telefonoPersona'];
            }
        }

        $c2pData = [
            'bancoPagador'    => $bancoPagador,
            'documentType'    => $data['documentType'] ?? null,
            'documentNumber'  => $data['documentNumber'] ?? null,
            'telefonoPersona' => $telefonoPersona,
            'otp'             => $otp,
            'ip_address'      => $data['ip_address'] ?? '127.0.0.1',
        ];

        return $this->gateway->processC2p($order, $c2pData);
    }
}
