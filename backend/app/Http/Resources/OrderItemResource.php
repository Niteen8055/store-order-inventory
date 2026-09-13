<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'product' => new ProductResource($this->whenLoaded('product')),
            'quantity' => (int) $this->quantity,
            'unit_price' => (string) $this->unit_price,
            'tax_percentage' => (string) $this->tax_percentage,
            'tax_amount' => (string) $this->tax_amount,
            'line_total' => (string) $this->line_total,
        ];
    }
}
