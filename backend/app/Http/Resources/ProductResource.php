<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'name' => $this->name,
            'code' => $this->code,
            'price' => (string) $this->price,
            'tax_percentage' => (string) $this->tax_percentage,
            'stock_on_hand' => (int) $this->stock_on_hand,
        ];
    }
}
