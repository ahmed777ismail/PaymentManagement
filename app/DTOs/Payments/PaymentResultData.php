<?php

namespace App\DTOs\Payments;

use App\Enums\PaymentStatus;

readonly class PaymentResultData
{
    public function __construct(
        public PaymentStatus $status,
        public ?string $gatewayReference = null,
        public ?string $failureReason = null,
    ) {}
}
