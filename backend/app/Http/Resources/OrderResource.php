<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'subtotal' => (string) $this->subtotal,
            'tax' => (string) $this->tax,
            'grand_total' => (string) $this->grand_total,
            'items' => OrderItemResource::collection($this->whenLoaded('orderItems')),
        ];
    }
}
