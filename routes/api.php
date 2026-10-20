<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CourierController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ShipmentsController;
use App\Http\Controllers\ShippingServiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('shipping-services', [ShippingServiceController::class, 'index']);
        Route::get('shipping-services/{id}', [ShippingServiceController::class, 'show']);

        Route::get('tracking/{tracking_number}', [ShipmentsController::class, 'track']);
        Route::get('shipments/{id}', [ShipmentsController::class, 'show']);
        Route::get('shipments/{id}/tracking', [ShipmentsController::class, 'tracking']);
        Route::get('shipments/{id}/delivery-proofs', [CourierController::class, 'proofs']);
        Route::get('payments/{id}/proof', [PaymentController::class, 'proof']);

        Route::middleware('role:customer')->group(function () {
            Route::post('shipments', [ShipmentsController::class, 'store']);
            Route::get('me/shipments', [ShipmentsController::class, 'me']);
            Route::patch('shipments/{id}/cancel', [ShipmentsController::class, 'cancel']);
            Route::post('shipments/{id}/payments', [PaymentController::class, 'pay']);
            Route::get('me/payments', [PaymentController::class, 'me']);
        });

        Route::middleware('role:staff,admin')->prefix('staff')->group(function () {
            Route::get('shipments', [ShipmentsController::class, 'staff']);
            Route::patch('shipments/{id}/confirm', [ShipmentsController::class, 'confirm']);
        });

        Route::middleware('role:courier')->group(function () {
            Route::get('courier/shipments', [CourierController::class, 'me']);
            Route::patch('courier/shipments/{id}/status', [CourierController::class, 'updateStatus']);
            Route::post('shipments/{id}/delivery-proofs', [CourierController::class, 'proof']);
        });

        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::post('shipping-services', [ShippingServiceController::class, 'store']);
            Route::patch('shipping-services/{id}', [ShippingServiceController::class, 'update']);
            Route::get('shipments', [ShipmentsController::class, 'admin']);
            Route::patch('shipments/{id}/assign', [ShipmentsController::class, 'assign']);
            Route::patch('payments/{id}/status', [PaymentController::class, 'verify']);
        });
    });
});
