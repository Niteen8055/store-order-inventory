<?php

namespace App\Domain\Order\Actions;

use App\Domain\Customer\Models\Customer;

class GetCustomerOrdersAction
{
    public function handle(string $email): Customer
    {
        return Customer::query()
            ->where('email', $email)
            ->with([
                'orders' => fn($query) => $query->orderBy('id', 'desc'),
                'orders.orderItems.product',
            ])
            ->firstOrFail();
    }
}
