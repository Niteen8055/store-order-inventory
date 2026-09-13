<?php

use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Actions\CreateOrderAction;
use App\Domain\Order\DTOs\CreateOrderData;
use App\Domain\Order\Exceptions\InsufficientStockException;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Order\Services\OrderCalculationService;
use App\Domain\Product\Models\Product;
use App\Jobs\SendOrderConfirmationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\Fluent\AssertableJson;

uses(RefreshDatabase::class);

it('creates an order from a valid payload and snapshots product pricing and tax', function () {
    Queue::fake();

    $product = Product::factory()->create([
        'price' => '10.00',
        'tax_percentage' => '8.00',
        'stock_on_hand' => 5,
    ]);

    $email = 'new.customer@example.com';
    $name = 'Ada Lovelace';

    $response = $this->postJson('/api/v1/orders', [
        'customer' => [
            'email' => $email,
            'name' => $name,
        ],
        'items' => [
            ['product_id' => $product->id, 'quantity' => 2],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.customer.email', $email)
        ->assertJsonPath('data.customer.name', $name)
        ->assertJsonPath('data.items.0.product_id', $product->id)
        ->assertJsonPath('data.items.0.quantity', 2)
        ->assertJsonPath('data.items.0.unit_price', '10.00')
        ->assertJsonPath('data.items.0.tax_percentage', '8.00');

    $this->assertDatabaseHas('customers', [
        'email' => $email,
        'name' => $name,
    ]);

    $order = Order::query()->first();
    $this->assertNotNull($order);
    $this->assertEquals('20.00', $order->subtotal);
    $this->assertEquals('1.60', $order->tax);
    $this->assertEquals('21.60', $order->grand_total);

    $this->assertDatabaseHas('order_items', [
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => '10.00',
        'tax_percentage' => '8.00',
        'tax_amount' => '1.60',
        'line_total' => '21.60',
    ]);

    $product->refresh();
    $this->assertSame(3, $product->stock_on_hand);

    Queue::assertPushed(SendOrderConfirmationJob::class, function ($job) use ($order) {
        return $job->orderId === $order->id;
    });
});

it('consolidates duplicate product lines and persists one order item per product', function () {
    Queue::fake();

    $product = Product::factory()->create([
        'price' => '10.00',
        'tax_percentage' => '8.00',
        'stock_on_hand' => 10,
    ]);

    $response = $this->postJson('/api/v1/orders', [
        'customer' => [
            'email' => 'duplicate@example.com',
            'name' => 'Duplicate Customer',
        ],
        'items' => [
            ['product_id' => $product->id, 'quantity' => 2],
            ['product_id' => $product->id, 'quantity' => 3],
        ],
    ]);

    $response->assertStatus(201);

    $order = Order::query()->first();
    $this->assertNotNull($order);

    $items = OrderItem::query()->where('order_id', $order->id)->get();
    $this->assertCount(1, $items);
    $this->assertSame(5, $items->first()->quantity);

    $product->refresh();
    $this->assertSame(5, $product->stock_on_hand);
});

it('returns insufficient stock as a clean conflict and does not persist partial records', function () {
    $product = Product::factory()->create([
        'price' => '10.00',
        'tax_percentage' => '8.00',
        'stock_on_hand' => 1,
    ]);

    $response = $this->postJson('/api/v1/orders', [
        'customer' => [
            'email' => 'short@example.com',
            'name' => 'Short Customer',
        ],
        'items' => [
            ['product_id' => $product->id, 'quantity' => 2],
        ],
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('error', 'insufficient_stock')
        ->assertJsonPath('product_id', $product->id)
        ->assertJsonPath('requested_quantity', 2)
        ->assertJsonPath('available_quantity', 1);

    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('order_items', 0);

    $product->refresh();
    $this->assertSame(1, $product->stock_on_hand);
});

it('rolls back an entire multi-line order when one product has insufficient stock', function () {
    $productA = Product::factory()->create([
        'price' => '10.00',
        'tax_percentage' => '8.00',
        'stock_on_hand' => 1,
    ]);
    $productB = Product::factory()->create([
        'price' => '20.00',
        'tax_percentage' => '8.00',
        'stock_on_hand' => 1,
    ]);

    $response = $this->postJson('/api/v1/orders', [
        'customer' => [
            'email' => 'rollback@example.com',
            'name' => 'Rollback Customer',
        ],
        'items' => [
            ['product_id' => $productA->id, 'quantity' => 1],
            ['product_id' => $productB->id, 'quantity' => 2],
        ],
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('error', 'insufficient_stock');

    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('order_items', 0);

    $productA->refresh();
    $productB->refresh();
    $this->assertSame(1, $productA->stock_on_hand);
    $this->assertSame(1, $productB->stock_on_hand);
});

it('returns a customer order history resource with related orders and items', function () {
    $customer = Customer::factory()->create([
        'name' => 'History Customer',
        'email' => 'history@example.com',
    ]);
    $otherCustomer = Customer::factory()->create([
        'name' => 'Other Customer',
        'email' => 'other@example.com',
    ]);

    $product = Product::factory()->create([
        'price' => '10.00',
        'tax_percentage' => '8.00',
        'stock_on_hand' => 10,
    ]);

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'subtotal' => '20.00',
        'tax' => '1.60',
        'grand_total' => '21.60',
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => '10.00',
        'tax_percentage' => '8.00',
        'tax_amount' => '1.60',
        'line_total' => '21.60',
    ]);

    $otherOrder = Order::factory()->create([
        'customer_id' => $otherCustomer->id,
        'subtotal' => '10.00',
        'tax' => '0.80',
        'grand_total' => '10.80',
    ]);

    $response = $this->getJson('/api/v1/customers/' . rawurlencode($customer->email) . '/orders');

    $response->assertStatus(200)
        ->assertJsonPath('data.email', $customer->email)
        ->assertJsonPath('data.name', $customer->name)
        ->assertJsonCount(1, 'data.orders')
        ->assertJsonPath('data.orders.0.id', $order->id)
        ->assertJsonPath('data.orders.0.items.0.product_id', $product->id);

    $this->assertArrayNotHasKey($otherCustomer->id, $response->json('data.orders.0'));
});

it('returns low-stock products using the default threshold and custom threshold validation', function () {
    Product::factory()->create([
        'name' => 'Low product',
        'stock_on_hand' => 3,
    ]);
    Product::factory()->create([
        'name' => 'Safe product',
        'stock_on_hand' => 5,
    ]);

    $defaultResponse = $this->getJson('/api/v1/products/low-stock');
    $defaultResponse->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Low product');

    $customResponse = $this->getJson('/api/v1/products/low-stock?threshold=4');
    $customResponse->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Low product');

    $invalid = $this->getJson('/api/v1/products/low-stock?threshold=0');
    $invalid->assertStatus(422)
        ->assertJsonPath('error', 'validation_failed');
});

it('returns a validation failure and does not create an order for invalid create-order requests', function () {
    $response = $this->postJson('/api/v1/orders', [
        'customer' => [
            'email' => 'not-an-email',
            'name' => '',
        ],
        'items' => [],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error', 'validation_failed');

    $this->assertDatabaseCount('orders', 0);
});

it('dispatches SendOrderConfirmationJob for a successful order creation and can load the created order from the queue payload', function () {
    Queue::fake();

    $product = Product::factory()->create([
        'price' => '10.00',
        'tax_percentage' => '8.00',
        'stock_on_hand' => 5,
    ]);

    $response = $this->postJson('/api/v1/orders', [
        'customer' => [
            'email' => 'queue@example.com',
            'name' => 'Queue Customer',
        ],
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
    ]);

    $response->assertStatus(201);

    $order = Order::query()->first();

    Queue::assertPushed(SendOrderConfirmationJob::class, function ($job) use ($order) {
        return $job->orderId === $order->id;
    });

    $job = new SendOrderConfirmationJob($order->id);
    Log::spy();
    $job->handle();

    Log::shouldHaveReceived('info')
        ->with(
            'Simulated order confirmation email sent.',
            Mockery::on(fn($payload) => $payload['order_id'] === $order->id && $payload['customer_email'] === 'queue@example.com')
        );
});

it('uses row locking semantics only when the test database supports row locking', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('This concurrency smoke test requires a MySQL row-lock-capable connection.');
    }

    $product = Product::factory()->create([
        'price' => '10.00',
        'tax_percentage' => '8.00',
        'stock_on_hand' => 1,
    ]);

    DB::beginTransaction();
    Product::query()->whereKey($product->id)->lockForUpdate()->first();

    try {
        $this->expect(fn() => (new CreateOrderAction(app(OrderCalculationService::class)))->handle(new CreateOrderData(
            'Concurrent Customer',
            'concurrent@example.com',
            [['product_id' => $product->id, 'quantity' => 1]],
        )))->toThrow(InsufficientStockException::class);
    } finally {
        DB::rollback();
    }
});
