<?php

namespace App\Domain\Order\Exceptions;

use RuntimeException;

final class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly int $productId,
        public readonly int $requestedQuantity,
        public readonly int $availableQuantity,
    ) {
        parent::__construct(
            "Insufficient stock for product {$this->productId}: requested {$this->requestedQuantity}, available {$this->availableQuantity}."
        );
    }
}
