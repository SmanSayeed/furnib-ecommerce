<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\Catalog\CategoryUiController;
use App\Http\Controllers\Admin\Catalog\ProductUiController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

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

require __DIR__.'/settings.php';
