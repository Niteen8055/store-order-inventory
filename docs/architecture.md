# Architecture

## Implemented Architecture

This repository implements a pragmatic, small-scope Domain-Driven Design inspired structure. It uses an action-based application layer that is intentionally simple:

- Domain model classes under `backend/app/Domain/{Domain}/Models/`
- Use case actions in `backend/app/Domain/{Domain}/Actions/`
- Validation request classes in `backend/app/Http/Requests/`
- Data-transfer objects in `backend/app/Domain/{Domain}/DTOs/`
- Order calculation and reusable domain arithmetic in `backend/app/Domain/{Domain}/Services/`
- API resources in `backend/app/Http/Resources/`
- Controllers in `backend/app/Http/Controllers/Api/V1/`
- Queue jobs in `backend/app/Jobs/`

This is appropriate for the assignment because the domain consists of a few clear entities: `Customer`, `Product`, `Order`, and `OrderItem`. A full enterprise architecture would introduce unnecessary repositories, buses, adapters, aggregate factories, event streaming, and layered application services not required to solve the assignment.

## Domain Structure

The domain organization follows the existing structure in the codebase:

```text
backend/app/Domain/Customer
backend/app/Domain/Product
backend/app/Domain/Order
```

Each domain contains the appropriate model and action structure. Product inventory is represented directly as `Product::stock_on_hand`. Customers are represented by unique email identity. Orders are created as header rows and linked to `OrderItem` child rows as snapshots of the product price, tax percentage, tax amount, and line total.

## Actions and Use Cases

The order creation workflow is implemented in `CreateOrderAction` in `backend/app/Domain/Order/Actions/CreateOrderAction.php`.

The action:

1. receives `CreateOrderData`,
2. consolidates item quantities by product ID,
3. locks the relevant product rows using `lockForUpdate()`,
4. validates product existence,
5. validates stock availability,
6. writes the customer, order, and order item records,
7. decrements stock,
8. and dispatches the queue job after the transaction commits.

This action is the correct place for the workflow because the controller is deliberately thin and because the lifecycle spans the database transaction and inventory rows.

## Form Requests

The input rules live in request classes.

`StoreOrderRequest` validates:

- `customer.email` must be a valid email,
- `customer.name` must be a non-empty string,
- `items` must be a non-empty array,
- `items.*.product_id` must exist in the products table,
- `items.*.quantity` must be a positive integer.

`LowStockRequest` validates the optional `threshold` query parameter as an integer greater than or equal to 1 and defaults it to `5`.

These request classes are intentionally simple and appropriate to the scope of the assignment.

## DTOs

`CreateOrderData` is the data-transfer object used to normalize an incoming order payload into a stable shape:

```php
CreateOrderData(string $customerName, string $customerEmail, array $items)
```

It also consolidates duplicate lines by product ID while preserving the correct order of quantities. This protects a simple API from duplicated line statements and removes pressure on the controller or resource layer.

## Eloquent Models

The application uses Eloquent models with relationship declarations:

- `Customer` has many `Order` rows.
- `Order` belongs to a `Customer` and has many `OrderItem` rows.
- `OrderItem` belongs to an `Order` and a `Product`.
- `Product` has many `OrderItem` rows.

Each model uses the repository’s normalized migration schema and carries the correct casts for decimals and integer numbers.

## Services

`OrderCalculationService` encapsulates repeated arithmetic for the line item and order total calculations. Its methods cover:

- `calculateLine()` for quantity, unit price, tax percent, tax amount, and line total.
- `calculateOrderTotals()` for subtotal, tax, and grand total.

These calculations are intentionally domain-appropriate service helper methods because they are reused by the action and should not be embedded in the controller or model.

## API Resources

The project has these resource classes:

- `OrderResource`
- `OrderItemResource`
- `CustomerResource`
- `ProductResource`

They serialize the exact response envelope the frontend expects and keep the controller thin. The shape returns `data` arrays and nested order/customer/product details in a predictable format.

## Controllers

Controllers remain thin. They accept input through Form Request objects, delegate domain operations to actions, and return resource responses.

Implemented controller classes:

- `OrderController` for `POST /api/v1/orders`
- `CustomerOrderController` for `GET /api/v1/customers/{email}/orders`
- `ProductController` for `GET /api/v1/products/low-stock`

## Jobs

The queue job class is `SendOrderConfirmationJob`. It is a `ShouldQueue` job that logs a simulated order confirmation using `Log::info(...)`. It intentionally avoids an SMTP dependency. It includes `tries = 3` and `backoff = 60` and reads the order through an eager relationship query before logging.

The order action dispatches the job with `afterCommit()` so the job is only queued after the order transaction successfully commits.

## Database Relationships

The relational model is represented directly in migrations:

- `customers` table with unique email constraint.
- `products` table with unique code and numeric inventory count.
- `orders` table with customer relation and totals.
- `order_items` table with product, quantity, item pricing, tax, and line-total snapshots.

Foreign keys in the migration files use `restrictOnDelete()` to avoid accidental deletion of a customer, product, or order row referenced by an order item.

## Order Workflow

The implemented order workflow is fully linear and consistent:

1. Client sends `POST /api/v1/orders`.
2. `StoreOrderRequest` validates the payload.
3. `CreateOrderAction` handles domain checks and transaction lifecycle.
4. `OrderItem` rows are stored as a snapshot of pricing and tax data.
5. Product stock is decremented within the same transaction.
6. `SendOrderConfirmationJob` is dispatched after commit.

This mirrors the assignment requirement without introducing a complex event system or enterprise asynchronous architecture.

## Concurrency Strategy

The critical concurrency strategy is the product row lock in the order creation path:

```php
Product::query()
    ->whereIn('id', array_keys($productQuantities))
    ->orderBy('id')
    ->lockForUpdate()
    ->get()
    ->keyBy('id');
```

The lock is taken inside one database transaction running in a separate MySQL connection context. A second request entering the same transaction shape must wait until the first transaction commits or rolls back. That means the second request sees the product’s updated stock. The only way a request can pass is if there remains enough stock. Otherwise it throws `InsufficientStockException` and the transaction rolls back.

The dedicated MySQL concurrency regression test is designed to prove the assignment requirement:

```text
Product stock = 1
Request A quantity = 1
Request B quantity = 1
Exactly one success
Exactly one insufficient-stock failure
Final stock = 0
No overselling
```

## Transaction Boundaries

The order creation lifecycle uses `DB::transaction(function () { ... }, 3)` inside the action. The transaction controls the entire write sequence: product evidence, order creation, line item creation, and product stock decrement. The queue dispatch is configured to happen `afterCommit()` so it is only emitted after the database state is durable.

## API Versioning

The repository uses a route file split that is consistent with the versioned contract:

```php
Route::prefix('v1')->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/customers/{email}/orders', [CustomerOrderController::class, 'index']);
    Route::get('/products/low-stock', [ProductController::class, 'lowStock']);
});
```

This is mounted through `backend/routes/api.php` and versioned by the `v1` prefix in `backend/routes/api_v1.php`. It intentionally avoids a duplicate `api/api/v1` prefix and uses route versioning directly rather than a more abstract API gateway pattern.

## Testing Approach

The project uses Pest in Laravel. Feature tests cover API routes and the order workflow, while tests also assert the queue dispatch and action outcomes. The dedicated MySQL concurrency test is a test-only integration test that runs the real `CreateOrderAction` to verify the row locking semantics in an independent database transaction. This is the safest and clearest evidence because SQLite cannot simulate this concurrency contract correctly.

## Why This Architecture Was Correct

This architecture is correct for the assignment because:

- It follows Laravel conventions.
- It isolates domain action behavior.
- It uses Eloquent models and migrations directly.
- It keeps the controller simple.
- It uses a queue-like job infrastructure with realistic database-backed expectations.
- It avoids enterprise bloat and only introduces enough abstraction for the businesses rules clearly in scope.

The team intentionally rejected more advanced patterns such as event sourcing, repository abstractions, custom query buses, or domain aggregate roots because the assignment did not require them and because they would obscure the requested order creation and inventory contract.

## Prompt Log

The repository contains actual prompt artifacts in `prompts/`:

- `01-assignment-analysis-prompt.png`
- `03-database-design-prompt.png`
- `03-database-design.png`
- `sample.md`

A screenshot for a missing expected prompt artifact should not be fabricated. The implementation documented here only cites the files that already exist in the repository.

## Final Review Summary

The implementation is consistent with the requested business scenario and the repository’s committed files. The architecture and tests remain coherent with the production implementation. No additional domain abstraction should be introduced before or during submission.
