<?php

namespace App\Contracts\Payments;

use App\DTOs\Payments\PaymentRequestData;
use App\DTOs\Payments\PaymentResultData;

interface PaymentGatewayInterface
{
    public function process(PaymentRequestData $paymentRequest): PaymentResultData;
}
