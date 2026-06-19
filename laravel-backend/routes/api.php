<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\OtpController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\ShippingZoneController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', fn () => response()->json([
        'status' => 'ok',
        'service' => 'furnib-api',
    ]));

    // Public branding/site settings for the storefront
    Route::get('settings', [SettingController::class, 'index']);

    // Storefront catalog (read-only)
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{slug}', [CategoryController::class, 'show']);
    Route::get('products/{slug}', [ProductController::class, 'show']);

    // Storefront checkout
    Route::get('shipping-zones', [ShippingZoneController::class, 'index']);
    Route::middleware('throttle:orders')->post('orders', [CheckoutController::class, 'store']);

    Route::middleware('auth:sanctum')->get('me', fn (Request $request) => response()->json([
        'id' => $request->user()->id,
        'email' => $request->user()->email,
    ]));

    // Customer mobile OTP auth (storefront).
    Route::middleware('throttle:otp')->post('auth/otp/request', [OtpController::class, 'request']);
    Route::middleware('throttle:auth')->post('auth/otp/verify', [OtpController::class, 'verify']);

    // Authenticated customer (Sanctum token scoped to the 'customer' ability).
    Route::middleware(['auth:customer', 'abilities:customer'])->get('auth/me', function (Request $request) {
        $customer = $request->user('customer');

        return response()->json([
            'id' => $customer->id,
            'name' => $customer->name,
            'mobile' => $customer->mobile,
            'email' => $customer->email,
        ]);
    });
});
