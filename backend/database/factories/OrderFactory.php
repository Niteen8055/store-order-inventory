<?php

namespace Database\Factories;

use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotalInCents = fake()->numberBetween(1000, 50000);
        $taxPercentage = fake()->randomElement([5, 12, 18]);
        $taxInCents = intdiv($subtotalInCents * $taxPercentage + 50, 100);

        return [
            'customer_id' => Customer::factory(),
            'subtotal' => $this->amountFromCents($subtotalInCents),
            'tax' => $this->amountFromCents($taxInCents),
            'grand_total' => $this->amountFromCents($subtotalInCents + $taxInCents),
        ];
    }

    private function amountFromCents(int $amountInCents): string
    {
        return sprintf('%d.%02d', intdiv($amountInCents, 100), $amountInCents % 100);
    }
}
