<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Product\Actions\GetLowStockProductsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\LowStockRequest;
use App\Http\Resources\ProductResource;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function __construct(
        private readonly GetLowStockProductsAction $getLowStockProductsAction,
    ) {}

    public function lowStock(LowStockRequest $request): JsonResponse
    {
        $products = $this->getLowStockProductsAction->handle($request->threshold());

        return ProductResource::collection($products)->response();
    }
}
