<?php

namespace App\Jobs;

use App\Domain\Order\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOrderConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public int $orderId)
    {
    }

    public function handle(): void
    {
        $order = Order::query()
            ->with(['customer', 'orderItems.product'])
            ->findOrFail($this->orderId);

        Log::info('Simulated order confirmation email sent.', [
            'order_id' => $order->id,
            'customer_email' => $order->customer?->email,
            'customer_name' => $order->customer?->name,
            'grand_total' => $order->grand_total,
            'items_count' => $order->orderItems->count(),
        ]);
    }
}
