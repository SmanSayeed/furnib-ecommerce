<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
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

require __DIR__.'/settings.php';
