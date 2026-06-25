<?php

namespace App\Support\Orders;

class OrderItemNormalizer
{
    /**
     * @param  list<array{product_name: string, quantity: int, price: int|float|string}>  $items
     * @return list<array{product_name: string, quantity: int, unit_price: float, line_total: float}>
     */
    public function normalize(array $items): array
    {
        return array_map(function (array $item): array {
            $unitPrice = round((float) $item['price'], 2);
            $quantity = (int) $item['quantity'];

            return [
                'product_name' => $item['product_name'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => round($quantity * $unitPrice, 2),
            ];
        }, $items);
    }

    /**
     * @param  list<array{line_total: float}>  $items
     */
    public function total(array $items): float
    {
        return round(array_sum(array_column($items, 'line_total')), 2);
    }
}
