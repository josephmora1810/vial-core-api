<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Entities\Order;
use App\Domain\Payments\ValueObjects\PaymentResult;

interface PaymentStrategyInterface
{
    public function process(Order $order, array $data, float $expectedAmountVes): PaymentResult;
}
