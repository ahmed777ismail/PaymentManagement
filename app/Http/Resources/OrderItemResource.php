<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_name' => $this->product_name,
            'quantity' => $this->quantity,
            'unit_price' => number_format((float) $this->unit_price, 2, '.', ''),
            'line_total' => number_format((float) $this->line_total, 2, '.', ''),
        ];
    }
}
