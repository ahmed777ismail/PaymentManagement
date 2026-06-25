<?php

namespace App\DTOs\Payments;

use App\Models\Order;

readonly class PaymentRequestData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public Order $order,
        public string $method,
        public float $amount,
        public string $currency,
        public ?array $metadata = null,
    ) {}
}
