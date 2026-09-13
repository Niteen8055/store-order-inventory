<?php

namespace Database\Seeders;

use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Product\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class StoreOrderInventorySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = collect([
            ['name' => 'Aarav Mehta', 'email' => 'aarav.mehta@example.test'],
            ['name' => 'Maya Sharma', 'email' => 'maya.sharma@example.test'],
            ['name' => 'Rohan Kapoor', 'email' => 'rohan.kapoor@example.test'],
            ['name' => 'Isha Nair', 'email' => 'isha.nair@example.test'],
            ['name' => 'Vikram Singh', 'email' => 'vikram.singh@example.test'],
        ])->mapWithKeys(fn (array $attributes): array => [
            $attributes['email'] => Customer::factory()->create($attributes),
        ]);

        $products = collect([
            ['name' => 'USB-C Charging Cable', 'code' => 'USB-C-CABLE-1M', 'price' => '12.99', 'tax_percentage' => '18.00', 'stock_on_hand' => 24],
            ['name' => 'Wireless Mouse', 'code' => 'WIRELESS-MOUSE', 'price' => '24.50', 'tax_percentage' => '18.00', 'stock_on_hand' => 8],
            ['name' => 'Mechanical Keyboard', 'code' => 'MECHANICAL-KEYBOARD', 'price' => '79.99', 'tax_percentage' => '18.00', 'stock_on_hand' => 10],
            ['name' => '27-inch Monitor', 'code' => 'MONITOR-27-INCH', 'price' => '249.99', 'tax_percentage' => '18.00', 'stock_on_hand' => 6],
            ['name' => 'Ergonomic Office Chair', 'code' => 'ERGONOMIC-CHAIR', 'price' => '159.99', 'tax_percentage' => '18.00', 'stock_on_hand' => 4],
            ['name' => 'LED Desk Lamp', 'code' => 'LED-DESK-LAMP', 'price' => '39.50', 'tax_percentage' => '5.00', 'stock_on_hand' => 12],
            ['name' => 'A5 Ruled Notebook', 'code' => 'A5-NOTEBOOK', 'price' => '4.25', 'tax_percentage' => '5.00', 'stock_on_hand' => 50],
            ['name' => 'Ceramic Coffee Mug', 'code' => 'CERAMIC-MUG', 'price' => '9.75', 'tax_percentage' => '5.00', 'stock_on_hand' => 20],
            ['name' => '1TB External SSD', 'code' => 'EXTERNAL-SSD-1TB', 'price' => '99.99', 'tax_percentage' => '18.00', 'stock_on_hand' => 7],
            ['name' => '1080p Webcam', 'code' => 'WEBCAM-1080P', 'price' => '54.00', 'tax_percentage' => '18.00', 'stock_on_hand' => 2],
        ])->mapWithKeys(fn (array $attributes): array => [
            $attributes['code'] => Product::factory()->create($attributes),
        ]);

        $this->seedOrder($customers, $products, 'aarav.mehta@example.test', [
            'MONITOR-27-INCH' => 1,
            'USB-C-CABLE-1M' => 2,
        ]);
        $this->seedOrder($customers, $products, 'aarav.mehta@example.test', [
            'MECHANICAL-KEYBOARD' => 1,
            'WIRELESS-MOUSE' => 1,
        ]);
        $this->seedOrder($customers, $products, 'aarav.mehta@example.test', [
            'EXTERNAL-SSD-1TB' => 1,
            'USB-C-CABLE-1M' => 1,
        ]);
        $this->seedOrder($customers, $products, 'maya.sharma@example.test', [
            'ERGONOMIC-CHAIR' => 1,
            'LED-DESK-LAMP' => 1,
        ]);
        $this->seedOrder($customers, $products, 'rohan.kapoor@example.test', [
            'A5-NOTEBOOK' => 4,
            'CERAMIC-MUG' => 2,
        ]);
        $this->seedOrder($customers, $products, 'isha.nair@example.test', [
            'WIRELESS-MOUSE' => 1,
            'USB-C-CABLE-1M' => 3,
            'A5-NOTEBOOK' => 3,
        ]);
        $this->seedOrder($customers, $products, 'vikram.singh@example.test', [
            'WEBCAM-1080P' => 1,
            'CERAMIC-MUG' => 1,
        ]);
    }

    /**
     * @param  Collection<string, Customer>  $customers
     * @param  Collection<string, Product>  $products
     * @param  array<string, int>  $items
     */
    private function seedOrder(Collection $customers, Collection $products, string $customerEmail, array $items): void
    {
        $orderItems = collect($items)->map(function (int $quantity, string $productCode) use ($products): array {
            /** @var Product $product */
            $product = $products->get($productCode);
            $unitPriceInCents = $this->amountToCents($product->price);
            $taxPercentageInBasisPoints = $this->amountToCents($product->tax_percentage);
            $subtotalInCents = $quantity * $unitPriceInCents;
            $taxInCents = intdiv($subtotalInCents * $taxPercentageInBasisPoints + 5000, 10000);

            return [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $this->amountFromCents($unitPriceInCents),
                'tax_percentage' => $product->tax_percentage,
                'tax_amount' => $this->amountFromCents($taxInCents),
                'line_total' => $this->amountFromCents($subtotalInCents + $taxInCents),
                'subtotal_in_cents' => $subtotalInCents,
                'tax_in_cents' => $taxInCents,
            ];
        });

        /** @var Customer $customer */
        $customer = $customers->get($customerEmail);
        $subtotalInCents = $orderItems->sum('subtotal_in_cents');
        $taxInCents = $orderItems->sum('tax_in_cents');

        $order = Order::factory()
            ->for($customer)
            ->create([
                'subtotal' => $this->amountFromCents($subtotalInCents),
                'tax' => $this->amountFromCents($taxInCents),
                'grand_total' => $this->amountFromCents($subtotalInCents + $taxInCents),
            ]);

        $orderItems->each(function (array $attributes) use ($order): void {
            /** @var Product $product */
            $product = $attributes['product'];

            OrderItem::factory()
                ->for($order)
                ->for($product)
                ->create([
                    'quantity' => $attributes['quantity'],
                    'unit_price' => $attributes['unit_price'],
                    'tax_percentage' => $attributes['tax_percentage'],
                    'tax_amount' => $attributes['tax_amount'],
                    'line_total' => $attributes['line_total'],
                ]);
        });
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
