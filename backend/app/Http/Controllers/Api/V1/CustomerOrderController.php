<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Order\Actions\GetCustomerOrdersAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use Illuminate\Http\JsonResponse;

class CustomerOrderController extends Controller
{
    public function __construct(
        private readonly GetCustomerOrdersAction $getCustomerOrdersAction,
    ) {}

    public function index(string $email): JsonResponse
    {
        $customer = $this->getCustomerOrdersAction->handle(rawurldecode($email));

        return (new CustomerResource($customer))->response();
    }
}
