<?php

namespace App\Exceptions;

use App\Domain\Order\Exceptions\InsufficientStockException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Register any exceptions for the application.
     */
    public function register(): void
    {
        $this->renderable(function (InsufficientStockException $exception, Request $request): JsonResponse {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => 'insufficient_stock',
                'product_id' => $exception->productId,
                'requested_quantity' => $exception->requestedQuantity,
                'available_quantity' => $exception->availableQuantity,
            ], Response::HTTP_CONFLICT);
        });

        $this->renderable(function (ModelNotFoundException $exception, Request $request): JsonResponse {
            return response()->json([
                'message' => 'The requested resource was not found.',
                'error' => 'resource_not_found',
            ], Response::HTTP_NOT_FOUND);
        });

        $this->renderable(function (ValidationException $exception, Request $request): JsonResponse {
            return response()->json([
                'message' => 'Validation failed.',
                'error' => 'validation_failed',
                'errors' => $exception->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        $this->renderable(function (Throwable $exception, Request $request): JsonResponse {
            return response()->json([
                'message' => 'An unexpected error occurred.',
                'error' => 'server_error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        });
    }
}
