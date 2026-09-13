<?php

use App\Http\Controllers\Api\V1\CustomerOrderController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/customers/{email}/orders', [CustomerOrderController::class, 'index']);
    Route::get('/products/low-stock', [ProductController::class, 'lowStock']);
});
