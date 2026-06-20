<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\Catalog\CategoryUiController;
use App\Http\Controllers\Admin\Catalog\ProductUiController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ShipmentController;
use App\Http\Controllers\Admin\ShippingZoneController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\InvoiceDownloadController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Public SEO endpoints.
Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [SeoController::class, 'robots'])->name('robots');

// Public product feed (Meta/Google Merchant).
Route::get('feed/products.csv', [FeedController::class, 'products'])->name('feed.products');

// Customer invoice download — signed URL only (handed out on the success page).
Route::get('invoice/{order}', [InvoiceDownloadController::class, 'show'])
    ->middleware('signed')->name('invoice.public');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'permission:audit.view'])
    ->get('admin/audit-logs', [AuditLogController::class, 'index'])
    ->name('admin.audit-logs');

// Admin catalog (JSON for now; Inertia UI to follow). Gated by RBAC.
Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('categories', [AdminCategoryController::class, 'index'])->middleware('permission:catalog.view');

    Route::middleware('permission:catalog.manage')->group(function () {
        Route::post('categories', [AdminCategoryController::class, 'store']);
        Route::put('categories/{category}', [AdminCategoryController::class, 'update']);
        Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy']);
    });

    // Products — specific routes before the {product} wildcard.
    Route::middleware('permission:catalog.view')->group(function () {
        Route::get('products', [AdminProductController::class, 'index']);
        Route::get('products/trashed', [AdminProductController::class, 'trashed']);
        Route::get('products/export', [AdminProductController::class, 'export']);
    });

    Route::middleware('permission:catalog.manage')->group(function () {
        Route::post('products', [AdminProductController::class, 'store']);
        Route::put('products/{product}', [AdminProductController::class, 'update']);
        Route::delete('products/{product}', [AdminProductController::class, 'destroy']);
        Route::post('products/{id}/restore', [AdminProductController::class, 'restore']);
        Route::delete('products/{id}/force', [AdminProductController::class, 'forceDelete']);
    });
});

// Admin catalog — Inertia UI (separate from the JSON API above).
Route::middleware('auth')->prefix('admin/catalog')->name('admin.')->group(function () {
    Route::get('categories', [CategoryUiController::class, 'index'])
        ->middleware('permission:catalog.view')->name('categories.index');

    Route::middleware('permission:catalog.manage')->group(function () {
        Route::get('categories/create', [CategoryUiController::class, 'create'])->name('categories.create');
        Route::post('categories', [CategoryUiController::class, 'store'])->name('categories.store');
        Route::get('categories/{category}/edit', [CategoryUiController::class, 'edit'])->name('categories.edit');
        Route::put('categories/{category}', [CategoryUiController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [CategoryUiController::class, 'destroy'])->name('categories.destroy');
    });

    // Products — specific routes before the {product} wildcard.
    Route::get('products', [ProductUiController::class, 'index'])
        ->middleware('permission:catalog.view')->name('products.index');
    Route::get('products/trashed', [ProductUiController::class, 'trashed'])
        ->middleware('permission:catalog.view')->name('products.trashed');

    Route::middleware('permission:catalog.manage')->group(function () {
        Route::get('products/create', [ProductUiController::class, 'create'])->name('products.create');
        Route::post('products', [ProductUiController::class, 'store'])->name('products.store');
        Route::get('products/{product}/edit', [ProductUiController::class, 'edit'])->name('products.edit');
        Route::put('products/{product}', [ProductUiController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductUiController::class, 'destroy'])->name('products.destroy');
        Route::post('products/{id}/restore', [ProductUiController::class, 'restore'])->name('products.restore');
        Route::delete('products/{id}/force', [ProductUiController::class, 'forceDelete'])->name('products.force');
    });
});

// Admin orders — Inertia UI.
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('orders', [OrderController::class, 'index'])
        ->middleware('permission:orders.view')->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])
        ->middleware('permission:orders.view')->name('orders.show');
    Route::get('orders/{order}/invoice', [InvoiceController::class, 'show'])
        ->middleware('permission:orders.view')->name('orders.invoice');
    Route::put('orders/{order}/status', [OrderController::class, 'updateStatus'])
        ->middleware('permission:orders.manage')->name('orders.status');

    // Courier consignment (SteadFast) — booking + tracking.
    Route::middleware('permission:orders.manage')->group(function () {
        Route::post('orders/{order}/ship', [ShipmentController::class, 'store'])->name('orders.ship');
        Route::post('orders/{order}/track', [ShipmentController::class, 'track'])->name('orders.track');
    });

    // Owner-only reversible Maintenance Lock.
    Route::put('maintenance', [MaintenanceController::class, 'update'])
        ->middleware('permission:maintenance.manage')->name('maintenance.update');
});

// Admin shipping — Inertia UI.
Route::middleware('auth')->prefix('admin/shipping')->name('admin.')->group(function () {
    Route::get('zones', [ShippingZoneController::class, 'index'])
        ->middleware('permission:orders.view')->name('shipping-zones.index');

    Route::middleware('permission:orders.manage')->group(function () {
        Route::get('zones/create', [ShippingZoneController::class, 'create'])->name('shipping-zones.create');
        Route::post('zones', [ShippingZoneController::class, 'store'])->name('shipping-zones.store');
        Route::get('zones/{shippingZone}/edit', [ShippingZoneController::class, 'edit'])->name('shipping-zones.edit');
        Route::put('zones/{shippingZone}', [ShippingZoneController::class, 'update'])->name('shipping-zones.update');
        Route::delete('zones/{shippingZone}', [ShippingZoneController::class, 'destroy'])->name('shipping-zones.destroy');
    });
});

require __DIR__.'/settings.php';
