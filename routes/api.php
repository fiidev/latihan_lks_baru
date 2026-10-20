<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ShipmentsController;
use App\Http\Controllers\ShippingServiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('logout', [AuthController::class, 'logout'])->middleware(['auth:sanctum']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('shipping-services/{id}', [ShippingServiceController::class, 'show']);
        Route::get('shipping-services', [ShippingServiceController::class, 'index']);
        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::post('shipping-services', [ShippingServiceController::class, 'store']);
            Route::patch('shipping-services/{id}', [ShippingServiceController::class, 'update']);
        });
        Route::middleware('role:customer')->group(function () {
            Route::post('shipments', [ShipmentsController::class, 'store']);
            Route::get('me/shipments', [ShipmentsController::class, 'me']);
            Route::patch('shipments/{id}', [ShipmentsController::class, 'update']);
        });
    });
});
