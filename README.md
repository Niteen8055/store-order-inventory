# Store Order & Inventory Mini-System

## Project Overview

This repository implements a Laravel 13 + Vue 3 + TypeScript mini-system for creating store orders, storing order/customer history, listing low-stock products, and protecting inventory from overselling through transactional MySQL row locking.

The backend is a pragmatic Laravel REST API in `backend/`. The frontend is a minimal Vue 3 + TypeScript single-page UI in `frontend/` that consumes the API using Axios.

## Problem Statement

A store needs a simple order creation workflow that:

- validates customer and product input,
- calculates order totals and taxes,
- saves an order and order items,
- reduces product stock safely,
- rejects overselling with a clean conflict response,
- returns customer order history,
- returns low-stock products based on a configurable threshold,
- and dispatches a queue-backed simulated order confirmation job.

## Solution Summary

The implementation uses the Laravel API stack and a small domain-oriented structure:

- `Customer` model and resource across the customer identity/email lookup path.
- `Product` model and resource for product metadata and stock.
- `Order` and `OrderItem` models with foreign-key relationships.
- An order create action that consolidates duplicate product lines, locks products with `lockForUpdate()`, validates stock, calculates totals, creates the order and order items, reduces stock, then schedules a queue job after commit.
- A customer history controller/action that returns all of a customer’s orders and line items.
- A low-stock controller/action that returns products below a configurable threshold.
- A Vue frontend that offers an order form, customer history lookup, and low-stock list UI.

## Technology Stack

### Backend

- Laravel 13
- PHP 8.3+
- MySQL application and test database
- Eloquent ORM
- REST API endpoints under `api/v1`
- Form Requests and API Resources
- Queue jobs via `QUEUE_CONNECTION=database`
- Pest tests

### Frontend

- Vue 3
- TypeScript
- Vite
- Axios

## Repository Structure

```text
backend/   Laravel REST API and tests
frontend/  Vue 3 + TypeScript application
prompts/   Actual prompt screenshots and sample prompt file
README.md  Root project overview and submission guidance
```

## Backend Setup

From the repository root:

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

Use the `.env` file to point to the application database.

Typical backend development command:

```bash
cd backend
php artisan serve --host=127.0.0.1 --port=8000
```

The API server is mounted through `backend/routes/api.php` and `backend/routes/api_v1.php`.

## Frontend Setup

```bash
cd frontend
npm install
npm run dev
```

Production build:

```bash
cd frontend
npm run build
```

## Database Setup

The repository expects MySQL for the application database and test database. The app’s test configuration points to:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=store_order_inventory
DB_USERNAME=your_mysql_username
DB_PASSWORD=your_mysql_password
```

The shipped `.env.example` uses:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=backend
DB_USERNAME=root
DB_PASSWORD=
```

The schema is implemented in migrations and normalized into customers, products, orders, and order_items.

## Test Setup

Run the feature suite from the backend directory:

```bash
cd backend
php artisan test
```

The installed repository test suite uses Pest and MySQL-backed test assertions. The concurrency test is an explicit MySQL integration regression test rather than a SQLite simulation.

## Queue Setup

Queue configuration is database-backed:

```env
QUEUE_CONNECTION=database
```

To process queue jobs locally:

```bash
cd backend
php artisan queue:work
```

Order confirmation is simulated by the `SendOrderConfirmationJob` which logs a message rather than sending SMTP mail.

## API Documentation

The implemented API routes are:

```text
GET|HEAD api/user
GET|HEAD api/v1/customers/{email}/orders
POST api/v1/orders
GET|HEAD api/v1/products/low-stock
```

The actual route source is in `backend/routes/api_v1.php`:

```php
Route::prefix('v1')->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/customers/{email}/orders', [CustomerOrderController::class, 'index']);
    Route::get('/products/low-stock', [ProductController::class, 'lowStock']);
});
```

Request shape:

```json
POST /api/v1/orders
{
  "customer": {
    "email": "customer@example.com",
    "name": "Customer Name"
  },
  "items": [
    { "product_id": 1, "quantity": 2 }
  ]
}
```

Customer history route:

```text
GET /api/v1/customers/{email}/orders
```

Low-stock route:

```text
GET /api/v1/products/low-stock?threshold=5
```

## Order Creation Workflow

The workflow follows the real implementation in `CreateOrderAction`:

1. Build `CreateOrderData` from the validated request.
2. Consolidate duplicate product lines by product ID.
3. Query the relevant product rows and lock them using `lockForUpdate()`.
4. Confirm all products exist.
5. Reject the order with `InsufficientStockException` if stock is insufficient.
6. Calculate unit price, tax percentage, tax amount, and line amount from the product pricing snapshot.
7. Create the customer with `firstOrCreate` by unique email.
8. Create the order and order items in the same database transaction.
9. Decrement product stock.
10. Dispatch `SendOrderConfirmationJob::dispatch($order->id)->afterCommit()`.

## Inventory Concurrency Strategy

The repository uses MySQL row locking in the order-creation action:

```php
Product::query()
    ->whereIn('id', array_keys($productQuantities))
    ->orderBy('id')
    ->lockForUpdate()
    ->get()
    ->keyBy('id');
```

This means the selected products are locked while the transaction reads then writes the stock snapshot. The order transaction is wrapped with `DB::transaction(function () { ... }, 3)` and can roll back any partial writes. The critical assignment scenario is:

```text
Product stock = 1
Request A -> quantity 1
Request B -> quantity 1
```

With MySQL row locking and a transaction boundary, exactly one request can succeed while the other sees an `InsufficientStockException`. Final stock becomes `0`. No overselling occurs. The dedicated MySQL regression test in the feature suite exercises that scenario.

## Queue/Job Behavior

The queue job is `SendOrderConfirmationJob`. It implements `ShouldQueue` and uses `tries = 3` and `backoff = 60`. The job reads the created order and related order items/products and logs a simulated confirmation message rather than sending mail through SMTP.

## Testing Strategy

The automated suite is written in Pest and exercises:

- order creation from valid payload,
- duplicate product line consolidation,
- insufficient stock conflict,
- multi-line rollback,
- customer history,
- low-stock endpoint,
- validation failure,
- job dispatch/resolution,
- and the MySQL concurrency regression.

The test environment must be MySQL-backed. Tests are not mocked to bypass row locking. They exercise the real production `CreateOrderAction` and `lockForUpdate()` behavior with the application database and test database pointed correctly.

## Architecture Decisions

The implementation deliberately uses a pragmatic DDD-friendly structure without enterprise abstractions:

- Domain models in `app/Domain/*/Models`.
- Use cases/actions in `app/Domain/*/Actions`.
- DTOs for request payload normalization.
- Request validation in `app/Http/Requests`.
- API resources for response shape.
- Services only where calculation support is reused by actions.
- Controllers that remain thin wrappers over actions.

This is appropriate for the assignment because the system is small and the logic is already directly represented by the database schema and domain models. More abstract patterns such as command buses, CQRS, repositories, event-sourcing, and heavy service orchestration would add complexity without solving a demonstrated project requirement.

## Assumptions

The implemented assumptions are:

- Customer identity uses unique email address as the natural lookup key.
- Duplicate product lines in one request are consolidated by product ID before processing.
- A multi-line order is all-or-nothing because it runs inside one database transaction.
- Low-stock threshold is read from the query parameter `threshold`; if omitted, the default is `5`.
- Order item pricing, tax, and line totals are stored as snapshots on the order item row immediately as part of the order creation transaction.
- Email confirmation is simulated through the queue job and a Laravel log entry; no SMTP system is configured.
- Authentication is out of scope and therefore not added.

## Trade-offs

- The queue job is simulated, not a real email provider integration.
- The API has no authentication or authorization layer.
- The frontend is intentionally lightweight and does not use a state-management library.
- Database-only queue processing is used rather than an external queue worker.

## Known Limitations

- No real SMTP or email delivery is implemented.
- No authentication, role model, or authorization is implemented.
- The queue worker is a simulated log-oriented job rather than an external service integration.
- The store only supports the current API contract and not a separate resource model.

## AI-Assisted Development Approach

This implementation used an AI-assisted development workflow grounded in the repository itself.

The development process used actual project-aware assistance through Laravel Boost and MCP-enabled workflow context for:

- requirements analysis,
- architecture exploration,
- database design analysis,
- implementation assistance,
- debugging,
- testing,
- and final integration review.

Final engineering decisions, especially the versioned API route shape and the MySQL row-locking regression strategy, were reviewed in practice before implementation. The role of AI was assistive and collaborative rather than replacing the final engineering decision.

## Prompt Log

The repository contains the following actual prompt screenshot and prompt artifacts in `prompts/`:

```text
## Prompt Log

The following screenshots are the actual AI prompts used during development:

- [01 – Assignment Analysis](prompts/01-assignment-analysis.png)
- [02 – Architecture Design](prompts/02-architecture-design.png)
- [03 – Database Design](prompts/03-database-design.png)
- [04 – Order Creation & Concurrency](prompts/04-order-creation-and-concurrency.png)
- [05 – API Endpoints](prompts/05-api-endpoints.png)
- [06 – Queue Job](prompts/06-queue-job.png)
- [07 – Final Integration Review](prompts/07-final-integration-review-01.png)
```

No fabricated remediation or synthetic screenshot has been added. The `prompts/` folder should be treated as the canonical evidence of the assignment’s AI-assisted prompt/log artifacts.

## Submission Instructions

Before submitting, review the repository and make sure:

```bash
cd backend
php artisan test
cd ../frontend
npm run build
cd ../backend
php artisan route:list --path=api
```

The final submission should preserve the existing production implementation and only document verified behavior. No new feature work should be introduced after the final review.
