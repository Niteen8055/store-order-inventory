<?php

namespace App\Domain\Customer\Models;

use App\Domain\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
