<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LowStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'threshold' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function threshold(): int
    {
        return (int) $this->query('threshold', 5);
    }
}
