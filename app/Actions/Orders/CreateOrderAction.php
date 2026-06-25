<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\Orders\OrderItemNormalizer;
use Illuminate\Support\Facades\DB;

class CreateOrderAction
{
    public function __construct(private readonly OrderItemNormalizer $orderItemNormalizer) {}

    /**
     * @param  array{customer: array{name: string, email: string, phone?: string|null}, currency?: string, items: list<array{product_name: string, quantity: int, price: int|float|string}>}  $data
     */
    public function execute(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data): Order {
            $items = $this->orderItemNormalizer->normalize($data['items']);

            $order = Order::query()->create([
                'user_id' => $user->id,
                'customer_name' => $data['customer']['name'],
                'customer_email' => $data['customer']['email'],
                'customer_phone' => $data['customer']['phone'] ?? null,
                'status' => OrderStatus::Pending,
                'total_amount' => $this->orderItemNormalizer->total($items),
                'currency' => strtoupper($data['currency'] ?? 'USD'),
            ]);

            $order->items()->createMany($items);

            return $order->load('items');
        });
    }
}
