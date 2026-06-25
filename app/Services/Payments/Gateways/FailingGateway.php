<?php

namespace App\Services\Payments\Gateways;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\DTOs\Payments\PaymentRequestData;
use App\DTOs\Payments\PaymentResultData;
use App\Enums\PaymentStatus;

class FailingGateway implements PaymentGatewayInterface
{
    public function process(PaymentRequestData $paymentRequest): PaymentResultData
    {
        return new PaymentResultData(
            status: PaymentStatus::Failed,
            failureReason: 'Simulated gateway failure.',
        );
    }
}
