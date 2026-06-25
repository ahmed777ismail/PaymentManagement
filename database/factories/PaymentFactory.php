<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => (string) Str::uuid(),
            'order_id' => Order::factory(),
            'status' => PaymentStatus::Successful,
            'method' => 'credit_card',
            'amount' => fake()->randomFloat(2, 10, 1000),
            'currency' => 'USD',
            'gateway_reference' => 'test_'.Str::uuid()->toString(),
            'failure_reason' => null,
            'metadata' => null,
            'processed_at' => now(),
        ];
    }
}
