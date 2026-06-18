<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\OwnerSeeder;
use Database\Seeders\PermissionRoleSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

it('bootstraps the owner from config with a hashed password and owner role', function () {
    config([
        'rbac.owner_email' => 'owner@furnib.test',
        'rbac.owner_bootstrap_password' => 'Boot$trapPass123',
    ]);

    $this->seed(OwnerSeeder::class);

    $owner = User::where('email', 'owner@furnib.test')->first();

    expect($owner)->not->toBeNull()
        ->and($owner->password)->not->toBe('Boot$trapPass123')
        ->and(Hash::check('Boot$trapPass123', $owner->password))->toBeTrue()
        ->and($owner->hasRole('owner'))->toBeTrue()
        ->and($owner->must_change_password)->toBeTrue()
        ->and($owner->two_factor_required)->toBeTrue();
});

it('aborts owner bootstrap when the email is missing', function () {
    config(['rbac.owner_email' => null, 'rbac.owner_bootstrap_password' => 'x']);

    $this->seed(OwnerSeeder::class);
})->throws(RuntimeException::class);

it('does not create a hidden duplicate owner on re-run', function () {
    config([
        'rbac.owner_email' => 'owner@furnib.test',
        'rbac.owner_bootstrap_password' => 'Boot$trapPass123',
    ]);

    $this->seed(OwnerSeeder::class);
    $this->seed(OwnerSeeder::class);

    expect(User::where('email', 'owner@furnib.test')->count())->toBe(1);
});
