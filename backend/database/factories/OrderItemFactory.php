<?php

namespace Database\Factories;

use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Product\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);
        $unitPriceInCents = fake()->numberBetween(500, 20000);
        $taxPercentage = fake()->randomElement([5, 12, 18]);
        $subtotalInCents = $quantity * $unitPriceInCents;
        $taxInCents = intdiv($subtotalInCents * $taxPercentage + 50, 100);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'unit_price' => $this->amountFromCents($unitPriceInCents),
            'tax_percentage' => number_format($taxPercentage, 2, '.', ''),
            'tax_amount' => $this->amountFromCents($taxInCents),
            'line_total' => $this->amountFromCents($subtotalInCents + $taxInCents),
        ];
    }

    private function amountFromCents(int $amountInCents): string
    {
        return sprintf('%d.%02d', intdiv($amountInCents, 100), $amountInCents % 100);
    }
}
