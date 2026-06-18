<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

it('seeds roles and permissions idempotently', function () {
    $this->seed(PermissionRoleSeeder::class); // run a second time

    expect(Role::count())->toBe(count(config('rbac.roles')))
        ->and(Permission::count())->toBe(count(config('rbac.permissions')));
});

it('denies the editor role the orders.manage permission', function () {
    $user = User::factory()->create();
    $user->assignRole('editor');

    expect($user->can('orders.manage'))->toBeFalse()
        ->and($user->can('catalog.manage'))->toBeTrue();
});

it('grants the owner role every permission', function () {
    $user = User::factory()->create();
    $user->assignRole('owner');

    foreach (config('rbac.permissions') as $permission) {
        expect($user->can($permission))->toBeTrue();
    }
});

it('matches the manager permission set from the matrix', function () {
    $user = User::factory()->create();
    $user->assignRole('manager');

    expect($user->can('orders.manage'))->toBeTrue()
        ->and($user->can('payments.view'))->toBeFalse()
        ->and($user->can('settings.manage'))->toBeFalse();
});
