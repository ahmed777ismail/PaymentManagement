<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;

class UpdateOrderStatusAction
{
    public function execute(Order $order, OrderStatus $status): Order
    {
        $order->update([
            'status' => $status,
        ]);

        return $order->refresh()->load('items');
    }
}
