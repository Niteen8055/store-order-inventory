<?php

namespace App\Domain\Order\Actions;

use App\Domain\Customer\Models\Customer;
use App\Domain\Order\DTOs\CreateOrderData;
use App\Domain\Order\Exceptions\InsufficientStockException;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderCalculationService;
use App\Domain\Product\Models\Product;
use App\Jobs\SendOrderConfirmationJob;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class CreateOrderAction
{
    public function __construct(private OrderCalculationService $orderCalculationService) {}

    public function handle(CreateOrderData $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            $productQuantities = $data->consolidatedProductQuantities();
            $products = Product::query()
                ->whereIn('id', array_keys($productQuantities))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $this->ensureProductsExist($productQuantities, $products->keys()->all());

            $lines = [];

            foreach ($productQuantities as $productId => $quantity) {
                /** @var Product $product */
                $product = $products->get($productId);

                if ($product->stock_on_hand < $quantity) {
                    throw new InsufficientStockException($product->id, $quantity, $product->stock_on_hand);
                }

                $lines[] = [
                    'product' => $product,
                    'calculation' => $this->orderCalculationService->calculateLine($product, $quantity),
                ];
            }

            $customer = Customer::firstOrCreate(
                ['email' => $data->customerEmail],
                ['name' => $data->customerName],
            );
            $totals = $this->orderCalculationService->calculateOrderTotals(
                array_column($lines, 'calculation')
            );
            $order = $customer->orders()->create($totals);

            foreach ($lines as $line) {
                /** @var Product $product */
                $product = $line['product'];
                $calculation = $line['calculation'];

                $order->orderItems()->create([
                    'product_id' => $product->id,
                    'quantity' => $calculation['quantity'],
                    'unit_price' => $calculation['unit_price'],
                    'tax_percentage' => $calculation['tax_percentage'],
                    'tax_amount' => $calculation['tax_amount'],
                    'line_total' => $calculation['line_total'],
                ]);
            }

            foreach ($lines as $line) {
                /** @var Product $product */
                $product = $line['product'];
                $product->decrement('stock_on_hand', $line['calculation']['quantity']);
            }

            SendOrderConfirmationJob::dispatch($order->id)->afterCommit();

            return $order;
        }, 3);
    }

    /**
     * @param  array<int, int>  $productQuantities
     * @param  list<int>  $foundProductIds
     */
    private function ensureProductsExist(array $productQuantities, array $foundProductIds): void
    {
        $missingProductIds = array_values(array_diff(array_keys($productQuantities), $foundProductIds));

        if ($missingProductIds !== []) {
            throw (new ModelNotFoundException)->setModel(Product::class, $missingProductIds);
        }
    }
}
