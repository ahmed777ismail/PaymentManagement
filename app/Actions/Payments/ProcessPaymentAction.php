<?php

namespace App\Actions\Payments;

use App\Contracts\Payments\PaymentGatewayResolverInterface;
use App\DTOs\Payments\PaymentRequestData;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessPaymentAction
{
    public function __construct(private readonly PaymentGatewayResolverInterface $paymentGatewayResolver) {}

    /**
     * @param  array{method: string, metadata?: array<string, mixed>|null}  $data
     */
    public function execute(Order $order, array $data): Payment
    {
        abort_if($order->status !== OrderStatus::Confirmed, 409, 'Payments can only be processed for confirmed orders.');

        return DB::transaction(function () use ($order, $data): Payment {
            $payment = Payment::query()->create([
                'payment_id' => (string) Str::uuid(),
                'order_id' => $order->id,
                'status' => PaymentStatus::Pending,
                'method' => $data['method'],
                'amount' => $order->total_amount,
                'currency' => $order->currency,
                'metadata' => $data['metadata'] ?? null,
            ]);

            $gateway = $this->paymentGatewayResolver->resolve($data['method']);
            $result = $gateway->process(new PaymentRequestData(
                order: $order,
                method: $data['method'],
                amount: (float) $order->total_amount,
                currency: $order->currency,
                metadata: $data['metadata'] ?? null,
            ));

            $payment->update([
                'status' => $result->status,
                'gateway_reference' => $result->gatewayReference,
                'failure_reason' => $result->failureReason,
                'processed_at' => now(),
            ]);

            return $payment->refresh();
        });
    }
}
