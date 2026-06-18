<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'account.secured'])->get('/secure-area', fn () => 'ok');
});

it('redirects to password change when a password change is required', function () {
    $user = User::factory()->create();
    $user->forceFill(['must_change_password' => true])->save();

    $this->actingAs($user)->get('/secure-area')
        ->assertRedirect(config('rbac.password_change_url'));
});

it('redirects to 2FA setup when 2FA is required but not confirmed', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'must_change_password' => false,
        'two_factor_required' => true,
        'two_factor_confirmed_at' => null,
    ])->save();

    $this->actingAs($user)->get('/secure-area')
        ->assertRedirect(config('rbac.two_factor_setup_url'));
});

it('allows access once password is changed and 2FA confirmed', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'must_change_password' => false,
        'two_factor_required' => true,
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->actingAs($user)->get('/secure-area')
        ->assertOk()
        ->assertSee('ok');
});
