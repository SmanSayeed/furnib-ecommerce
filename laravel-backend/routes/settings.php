<?php

use App\Http\Controllers\Settings\MarketingSettingController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SiteSettingController;
use App\Http\Controllers\Settings\SmtpSettingController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

// Branding / site settings — owner & admin only.
Route::middleware(['auth', 'permission:settings.manage'])->group(function () {
    Route::get('settings/site', [SiteSettingController::class, 'edit'])->name('site-settings.edit');
    Route::post('settings/site', [SiteSettingController::class, 'update'])->name('site-settings.update');

    // SMTP transport settings + deliverability test.
    Route::get('settings/smtp', [SmtpSettingController::class, 'edit'])->name('smtp-settings.edit');
    Route::post('settings/smtp', [SmtpSettingController::class, 'update'])->name('smtp-settings.update');
    Route::post('settings/smtp/test', [SmtpSettingController::class, 'test'])->name('smtp-settings.test');
});

// Marketing / analytics settings — marketer & admin.
Route::middleware(['auth', 'permission:marketing.manage'])->group(function () {
    Route::get('settings/marketing', [MarketingSettingController::class, 'edit'])->name('marketing-settings.edit');
    Route::post('settings/marketing', [MarketingSettingController::class, 'update'])->name('marketing-settings.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
