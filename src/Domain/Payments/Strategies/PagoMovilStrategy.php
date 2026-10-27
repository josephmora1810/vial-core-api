<?php

declare(strict_types=1);

namespace App\Domain\Payments\Strategies;

use App\Domain\Payments\Entities\Order;
use App\Domain\Payments\Services\{PaymentGatewayInterface, PaymentStrategyInterface};
use App\Domain\Payments\ValueObjects\{PaymentReference, PaymentResult};
use App\Domain\Users\ValueObjects\{NationalId, Phone};
use InvalidArgumentException;

class PagoMovilStrategy implements PaymentStrategyInterface
{
    public function __construct(protected PaymentGatewayInterface $gateway) {}

    public function process(Order $order, array $data, float $expectedAmountVes): PaymentResult
    {
        $rawIdentificador = trim((string)($data['identificadorPersona'] ?? ''));
        try {
            $identificador = NationalId::fromString($rawIdentificador)->toCompactFormat();
        } catch (InvalidArgumentException) {
            $identificador = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawIdentificador) ?? '');
        }

        $telefono = null;
        if (!empty($data['telefono'])) {
            try {
                $telefono = Phone::fromString((string)$data['telefono'])->toNationalFormat();
            } catch (InvalidArgumentException) {
                $telefono = (string)$data['telefono'];
            }
        }

        $rawReference = (string)($data['reference'] ?? '');
        $reference = (string)PaymentReference::fromString($rawReference);
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
