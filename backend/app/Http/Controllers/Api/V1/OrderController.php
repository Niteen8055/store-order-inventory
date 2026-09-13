<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Order\Actions\CreateOrderAction;
use App\Domain\Order\DTOs\CreateOrderData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private readonly CreateOrderAction $createOrderAction,
    ) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $dto = new CreateOrderData(
            customerName: $request->validatedData()['customerName'],
            customerEmail: $request->validatedData()['customerEmail'],
            items: $request->validatedData()['items'],
        );

        $order = $this->createOrderAction->handle($dto);

        return (new OrderResource($order->load(['customer', 'orderItems.product'])))
            ->response()
            ->setStatusCode(201);
    }
}
