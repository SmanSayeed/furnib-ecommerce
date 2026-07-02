<?php

use App\Http\Controllers\Settings\FooterDetailController;
use App\Http\Controllers\Settings\FooterSocialController;
use App\Http\Controllers\Settings\IntegrationSettingController;
use App\Http\Controllers\Settings\MarketingSettingController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SiteSettingController;
use App\Http\Controllers\Settings\SmtpSettingController;
use App\Http\Controllers\Settings\StorageSettingController;
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

    // Footer settings — social links + footer details (split out of site settings).
    Route::get('settings/footer/social', [FooterSocialController::class, 'edit'])->name('footer-social.edit');
    Route::post('settings/footer/social', [FooterSocialController::class, 'update'])->name('footer-social.update');
    Route::get('settings/footer/details', [FooterDetailController::class, 'edit'])->name('footer-details.edit');
    Route::post('settings/footer/details', [FooterDetailController::class, 'update'])->name('footer-details.update');
    // Toggle a published page in/out of the storefront footer.
    Route::patch('settings/footer/pages/{page}', [FooterDetailController::class, 'togglePage'])->name('footer-details.toggle-page');

    // SMTP transport settings + deliverability test.
    Route::get('settings/smtp', [SmtpSettingController::class, 'edit'])->name('smtp-settings.edit');
    Route::post('settings/smtp', [SmtpSettingController::class, 'update'])->name('smtp-settings.update');
    Route::post('settings/smtp/test', [SmtpSettingController::class, 'test'])->name('smtp-settings.test');

    // Payment / courier gateway credentials (encrypted secrets).
    Route::get('settings/integrations', [IntegrationSettingController::class, 'edit'])->name('integrations.edit');
    Route::post('settings/sslcommerz', [IntegrationSettingController::class, 'updateSslcommerz'])->name('sslcommerz-settings.update');
    Route::post('settings/steadfast', [IntegrationSettingController::class, 'updateSteadfast'])->name('steadfast-settings.update');

    // Media storage: driver toggle + Cloudflare R2 connection (encrypted keys).
    Route::get('settings/storage', [StorageSettingController::class, 'edit'])->name('storage-settings.edit');
    Route::post('settings/storage', [StorageSettingController::class, 'update'])->name('storage-settings.update');
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
