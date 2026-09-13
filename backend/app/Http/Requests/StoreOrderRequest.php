<?php

namespace App\Http\Requests;

use App\Domain\Product\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer.email' => ['required', 'email'],
            'customer.name' => ['required', 'string', 'min:1'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'min:1', Rule::exists(Product::class, 'id')],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function validatedData(): array
    {
        return [
            'customerName' => $this->input('customer.name'),
            'customerEmail' => $this->input('customer.email'),
            'items' => array_map(fn(array $item): array => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
            ], $this->input('items', [])),
        ];
    }
}
