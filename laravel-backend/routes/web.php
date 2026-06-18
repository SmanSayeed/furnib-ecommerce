<?php

use App\Http\Controllers\Admin\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth', 'permission:audit.view'])
    ->get('admin/audit-logs', [AuditLogController::class, 'index'])
    ->name('admin.audit-logs');

require __DIR__.'/settings.php';
