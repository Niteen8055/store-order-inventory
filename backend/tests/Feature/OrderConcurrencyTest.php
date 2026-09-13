<?php

use App\Domain\Order\Actions\CreateOrderAction;
use App\Domain\Order\DTOs\CreateOrderData;
use App\Domain\Order\Exceptions\InsufficientStockException;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use App\Domain\Order\Services\OrderCalculationService;
use App\Domain\Product\Models\Product;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

it('allows exactly one concurrent order to consume the last unit of stock', function () {
    // Arrange
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped(
            'This concurrency test requires a MySQL row-lock-capable connection.'
        );
    }

    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped(
            'This concurrency test requires the pcntl extension.'
        );
    }

    $product = Product::factory()->create([
        'price' => '10.00',
        'tax_percentage' => '8.00',
        'stock_on_hand' => 1,
    ]);

    $testToken = (string) Str::uuid();

    $directory = storage_path('framework/testing/concurrency');

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $readyA = $directory . '/' . $testToken . '-ready-a';
    $readyB = $directory . '/' . $testToken . '-ready-b';
    $start = $directory . '/' . $testToken . '-start';
    $resultA = $directory . '/' . $testToken . '-result-a.json';
    $resultB = $directory . '/' . $testToken . '-result-b.json';

    foreach ([$readyA, $readyB, $start, $resultA, $resultB] as $file) {
        @unlink($file);
    }

    $requests = [
        [
            'email' => $testToken . '-a@example.com',
            'ready' => $readyA,
            'result' => $resultA,
        ],
        [
            'email' => $testToken . '-b@example.com',
            'ready' => $readyB,
            'result' => $resultB,
        ],
    ];

    $pids = [];

    // Act
    foreach ($requests as $request) {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('Unable to fork concurrency test process.');
        }

        if ($pid === 0) {
            try {
                /*
                 * Each child must have its own DB connection.
                 * The parent connection must never be reused after fork.
                 */
                DB::disconnect();
                DB::purge();
                DB::reconnect();

                /*
                 * Prevent the queue job from creating unrelated queue-table
                 * side effects during this concurrency test.
                 */
                Queue::fake();

                touch($request['ready']);

                /*
                 * Wait until the parent confirms both workers are ready.
                 * This gives both workers the same starting point.
                 */
                $deadline = microtime(true) + 10;

                while (! file_exists($start)) {
                    if (microtime(true) > $deadline) {
                        throw new RuntimeException(
                            'Concurrency test start barrier timed out.'
                        );
                    }

                    usleep(10_000);
                }

                $order = app(CreateOrderAction::class)->handle(
                    new CreateOrderData(
                        'Concurrent Customer',
                        $request['email'],
                        [
                            [
                                'product_id' => $product->id,
                                'quantity' => 1,
                            ],
                        ],
                    )
                );

                file_put_contents(
                    $request['result'],
                    json_encode([
                        'status' => 'success',
                        'order_id' => $order->id,
                    ], JSON_THROW_ON_ERROR),
                    LOCK_EX
                );

                exit(0);
            } catch (InsufficientStockException) {
                file_put_contents(
                    $request['result'],
                    json_encode([
                        'status' => 'insufficient_stock',
                    ], JSON_THROW_ON_ERROR),
                    LOCK_EX
                );

                exit(0);
            } catch (Throwable $exception) {
                file_put_contents(
                    $request['result'],
                    json_encode([
                        'status' => 'error',
                        'exception' => $exception::class,
                        'message' => $exception->getMessage(),
                    ], JSON_THROW_ON_ERROR),
                    LOCK_EX
                );

                exit(1);
            }
        }

        $pids[] = $pid;
    }

    try {
        /*
         * Wait until both child processes have established their own
         * database connection and reached the barrier.
         */
        $deadline = microtime(true) + 10;

        while (! file_exists($readyA) || ! file_exists($readyB)) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException(
                    'Concurrency test worker readiness timed out.'
                );
            }

            usleep(10_000);
        }

        /*
         * Release both workers as close together as possible.
         */
        touch($start);

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        if (! file_exists($resultA) || ! file_exists($resultB)) {
            throw new RuntimeException(
                'Concurrency test workers did not produce both result files.'
            );
        }

        $results = [
            json_decode(
                file_get_contents($resultA),
                true,
                512,
                JSON_THROW_ON_ERROR
            ),
            json_decode(
                file_get_contents($resultB),
                true,
                512,
                JSON_THROW_ON_ERROR
            ),
        ];

        // Assert
        $successes = collect($results)
            ->where('status', 'success')
            ->count();

        $insufficientStockFailures = collect($results)
            ->where('status', 'insufficient_stock')
            ->count();

        $unexpectedFailures = collect($results)
            ->where('status', 'error')
            ->values()
            ->all();

        expect($unexpectedFailures)->toBe([]);
        expect($successes)->toBe(1);
        expect($insufficientStockFailures)->toBe(1);

        $product->refresh();

        expect($product->stock_on_hand)->toBe(0);
        expect(Order::query()->count())->toBe(1);
        expect(OrderItem::query()->count())->toBe(1);
    } finally {
        foreach ([$readyA, $readyB, $start, $resultA, $resultB] as $file) {
            @unlink($file);
        }
    }
});
