<?php

namespace App\Domain\Product\Actions;

use App\Domain\Product\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class GetLowStockProductsAction
{
    public function handle(int $threshold = 5): Collection
    {
        return Product::query()
            ->where('stock_on_hand', '<', $threshold)
            ->orderBy('id')
            ->get();
    }
}
