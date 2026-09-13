<?php

namespace App\Domain\Product\Models;

use App\Domain\Order\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'price',
        'tax_percentage',
        'stock_on_hand',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'tax_percentage' => 'decimal:2',
            'stock_on_hand' => 'integer',
        ];
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
