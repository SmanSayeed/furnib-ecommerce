<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\OtpController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CollectController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\MarketingController;
use App\Http\Controllers\Api\NewsletterController;
use App\Http\Controllers\Api\OrderStatusController;
use App\Http\Controllers\Api\PageController as ApiPageController;
use App\Http\Controllers\Api\Payment\PayPageController;
use App\Http\Controllers\Api\Payment\SslController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductShippingZoneController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\ShippingZoneController;
use App\Http\Controllers\Api\Sms\DlrController;
use App\Http\Controllers\Api\TrackingController;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', fn () => response()->json([
        'status' => 'ok',
        'service' => 'furnib-api',
    ]));

    // Public branding/site settings for the storefront
    Route::get('settings', [SettingController::class, 'index']);

    // Public analytics IDs (no secrets).
    Route::get('marketing', [MarketingController::class, 'index']);

    // Public maintenance flag.
    Route::get('maintenance', [MaintenanceController::class, 'index']);

    // Published CMS pages (footer links → /p/{slug}).
    Route::get('pages', [ApiPageController::class, 'index']);
    Route::get('pages/{slug}', [ApiPageController::class, 'show']);

    // Storefront pageview beacon.
    Route::middleware('throttle:tracking')->post('track', [TrackingController::class, 'store']);

    // Server-side tagging beacon (browser → Meta Conversions API).
    Route::middleware('throttle:tracking')->post('collect', [CollectController::class, 'store']);

    // Storefront catalog (read-only)
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{slug}', [CategoryController::class, 'show']);
    // Header typeahead — rate-limited; specific route before the {slug} wildcard.
    Route::middleware('throttle:60,1')->get('products', [ProductController::class, 'search']);
    Route::get('products/{slug}', [ProductController::class, 'show']);
    // Per-product shipping zones (base + this product's per-unit extra).
    Route::get('products/{slug}/shipping-zones', [ProductShippingZoneController::class, 'index']);

    // Newsletter subscription (storefront footer). Rate-limited per IP.
    Route::middleware('throttle:6,1')->post('newsletter', [NewsletterController::class, 'store']);

    // Storefront checkout
    Route::get('shipping-zones', [ShippingZoneController::class, 'index']);
    Route::middleware('throttle:orders')->post('orders', [CheckoutController::class, 'store']);

    // "What do I still owe?" — a shopper checks their own order's paid/due state
    // after returning from the gateway. Guarded by order_no + their mobile (no
    // IDOR), rate-limited. Read-only, money fields only.
    Route::middleware('throttle:30,1')->post('orders/{order_no}/status', [OrderStatusController::class, 'show']);

    // Self-service pay page summary — gated by the signed link token (no IDOR).
    Route::middleware('throttle:30,1')->get('pay/{order_no}/summary', [PayPageController::class, 'summary']);

    Route::middleware('auth:sanctum')->get('me', fn (Request $request) => response()->json([
        'id' => $request->user()->id,
        'email' => $request->user()->email,
    ]));

    // Customer mobile OTP auth (storefront).
    Route::middleware('throttle:otp')->post('auth/otp/request', [OtpController::class, 'request']);
    Route::middleware('throttle:auth')->post('auth/otp/verify', [OtpController::class, 'verify']);

    // SSLCommerz payments. `init` starts a session; the rest are gateway
    // callbacks re-validated server-side before any money is recorded.
    Route::prefix('payment/ssl')->group(function () {
        Route::middleware('throttle:orders')->post('init', [SslController::class, 'init']);
        Route::post('success', [SslController::class, 'success'])->name('api.payment.ssl.success');
        Route::post('fail', [SslController::class, 'fail'])->name('api.payment.ssl.fail');
        Route::post('cancel', [SslController::class, 'cancel'])->name('api.payment.ssl.cancel');
        Route::post('ipn', [SslController::class, 'ipn'])->name('api.payment.ssl.ipn');
    });

    // Automas SMS delivery reports (DLR). The secret {token} authenticates the
    // caller; {outcome} is fixed by which URL Automas was given. GET or POST — we
    // don't know which Automas uses, so accept both. Rate-limited against abuse.
    Route::middleware('throttle:120,1')
        ->match(['get', 'post'], 'sms/dlr/{token}/{outcome}', [DlrController::class, 'handle'])
        ->where('outcome', 'success|failed')
        ->name('api.sms.dlr');

    // Authenticated customer (Sanctum token scoped to the 'customer' ability).
    // The bearer token's tokenable is a Customer; fetch it explicitly so the
    // type is concrete (the default guard model is User).
    Route::middleware(['auth:sanctum', 'abilities:customer'])->get('auth/me', function (Request $request) {
        $customer = Customer::query()->whereKey($request->user()?->getKey())->firstOrFail();

        return response()->json([
            'id' => $customer->id,
            'name' => $customer->name,
            'mobile' => $customer->mobile,
            'email' => $customer->email,
        ]);
    });
});
