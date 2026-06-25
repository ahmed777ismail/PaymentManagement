<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\Orders\OrderItemNormalizer;
use Illuminate\Support\Facades\DB;

class UpdateOrderAction
{
    public function __construct(private readonly OrderItemNormalizer $orderItemNormalizer) {}

    /**
     * @param  array{customer: array{name: string, email: string, phone?: string|null}, currency?: string, items: list<array{product_name: string, quantity: int, price: int|float|string}>}  $data
     */
    public function execute(Order $order, array $data): Order
    {
        abort_if($order->status !== OrderStatus::Pending, 409, 'Only pending orders can be modified.');

        return DB::transaction(function () use ($order, $data): Order {
            $items = $this->orderItemNormalizer->normalize($data['items']);

            $order->update([
                'customer_name' => $data['customer']['name'],
                'customer_email' => $data['customer']['email'],
                'customer_phone' => $data['customer']['phone'] ?? null,
                'total_amount' => $this->orderItemNormalizer->total($items),
                'currency' => strtoupper($data['currency'] ?? $order->currency),
            ]);

            $order->items()->delete();
            $order->items()->createMany($items);

            return $order->refresh()->load('items');
        });
    }
}
