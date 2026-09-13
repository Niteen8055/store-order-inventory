<?php

namespace Database\Factories;

use App\Domain\Product\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'code' => fake()->unique()->bothify('PRD-####??'),
            'price' => number_format(fake()->numberBetween(500, 50000) / 100, 2, '.', ''),
            'tax_percentage' => fake()->randomElement(['0.00', '5.00', '12.00', '18.00']),
            'stock_on_hand' => fake()->numberBetween(5, 100),
        ];
    }
}
