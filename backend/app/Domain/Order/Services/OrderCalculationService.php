<?php

namespace App\Domain\Order\Services;

use App\Domain\Product\Models\Product;

class OrderCalculationService
{
    /**
     * @return array{
     *     quantity: int,
     *     unit_price: string,
     *     tax_percentage: string,
     *     tax_amount: string,
     *     line_total: string,
     *     subtotal_in_cents: int,
     *     tax_in_cents: int
     * }
     */
    public function calculateLine(Product $product, int $quantity): array
    {
        $unitPriceInCents = $this->amountToCents($product->price);
        $taxPercentageInBasisPoints = $this->amountToCents($product->tax_percentage);
        $subtotalInCents = $unitPriceInCents * $quantity;
        $taxInCents = intdiv($subtotalInCents * $taxPercentageInBasisPoints + 5000, 10000);

        return [
            'quantity' => $quantity,
            'unit_price' => $this->amountFromCents($unitPriceInCents),
            'tax_percentage' => $product->tax_percentage,
            'tax_amount' => $this->amountFromCents($taxInCents),
            'line_total' => $this->amountFromCents($subtotalInCents + $taxInCents),
            'subtotal_in_cents' => $subtotalInCents,
            'tax_in_cents' => $taxInCents,
        ];
    }

    /**
     * @param  list<array{subtotal_in_cents: int, tax_in_cents: int}>  $lines
     * @return array{subtotal: string, tax: string, grand_total: string}
     */
    public function calculateOrderTotals(array $lines): array
    {
        $subtotalInCents = array_sum(array_column($lines, 'subtotal_in_cents'));
        $taxInCents = array_sum(array_column($lines, 'tax_in_cents'));

        return [
            'subtotal' => $this->amountFromCents($subtotalInCents),
            'tax' => $this->amountFromCents($taxInCents),
            'grand_total' => $this->amountFromCents($subtotalInCents + $taxInCents),
        ];
    }

    private function amountToCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function amountFromCents(int $amountInCents): string
    {
        return sprintf('%d.%02d', intdiv($amountInCents, 100), $amountInCents % 100);
    }
}
