<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CategoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', fn () => response()->json([
        'status' => 'ok',
        'service' => 'furnib-api',
    ]));

    // Storefront catalog (read-only)
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{slug}', [CategoryController::class, 'show']);

    Route::middleware('auth:sanctum')->get('me', fn (Request $request) => response()->json([
        'id' => $request->user()->id,
        'email' => $request->user()->email,
    ]));

    // Placeholder throttled endpoint; real OTP logic arrives in Phase 4.
    Route::middleware('throttle:otp')->post('otp/request', fn () => response()->json([
        'sent' => true,
    ]));
});
