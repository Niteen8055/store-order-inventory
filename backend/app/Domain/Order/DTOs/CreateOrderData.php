<?php

namespace App\Domain\Order\DTOs;

use InvalidArgumentException;

final readonly class CreateOrderData
{
    /**
     * @param  list<array{product_id: int, quantity: int}>  $items
     */
    public function __construct(
        public string $customerName,
        public string $customerEmail,
        public array $items,
    ) {}

    /**
     * @return array<int, int>
     */
    public function consolidatedProductQuantities(): array
    {
        if ($this->items === []) {
            throw new InvalidArgumentException('An order must contain at least one product line.');
        }

        $productQuantities = [];

        foreach ($this->items as $item) {
            $productId = $item['product_id'];
            $quantity = $item['quantity'];

            if ($productId <= 0 || $quantity <= 0) {
                throw new InvalidArgumentException('Product IDs and quantities must be positive integers.');
            }

            $productQuantities[$productId] = ($productQuantities[$productId] ?? 0) + $quantity;
        }

        ksort($productQuantities, SORT_NUMERIC);

        return $productQuantities;
    }
}
