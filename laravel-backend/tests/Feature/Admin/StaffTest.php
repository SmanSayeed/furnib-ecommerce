<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

function staffAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin'); // has users.manage

    return $user;
}

function staffOwner(): User
{
    $user = User::factory()->create();
    $user->assignRole('owner');

    return $user;
}

it('shows the staff list to users.manage staff', function () {
    actingAs(staffAdmin())->get('/admin/staff')->assertOk();
});

it('blocks the staff list for users without users.manage', function () {
    $user = User::factory()->create();
    $user->assignRole('marketer');

    actingAs($user)->get('/admin/staff')->assertForbidden();
});

it('changes another user role', function () {
    $target = User::factory()->create();
    $target->assignRole('editor');

    actingAs(staffAdmin())
        ->put("/admin/staff/{$target->id}/role", ['role' => 'manager'])
        ->assertRedirect();

    expect($target->fresh()->getRoleNames()->first())->toBe('manager');
});

it('rejects an unknown role', function () {
    $target = User::factory()->create();
    $target->assignRole('editor');

    actingAs(staffAdmin())
        ->put("/admin/staff/{$target->id}/role", ['role' => 'superduper'])
        ->assertSessionHasErrors('role');
});

it('forbids changing your own role', function () {
    $admin = staffAdmin();

    actingAs($admin)
        ->put("/admin/staff/{$admin->id}/role", ['role' => 'manager'])
        ->assertForbidden();

    expect($admin->fresh()->getRoleNames()->first())->toBe('admin');
});

it('forbids a non-owner from granting the owner role', function () {
    $target = User::factory()->create();
    $target->assignRole('editor');

    actingAs(staffAdmin())
        ->put("/admin/staff/{$target->id}/role", ['role' => 'owner'])
        ->assertForbidden();

    expect($target->fresh()->hasRole('owner'))->toBeFalse();
});

it('lets the owner grant the owner role', function () {
    $target = User::factory()->create();
    $target->assignRole('editor');

    actingAs(staffOwner())
        ->put("/admin/staff/{$target->id}/role", ['role' => 'owner'])
        ->assertRedirect();

    expect($target->fresh()->hasRole('owner'))->toBeTrue();
});

it('forbids demoting the owner account', function () {
    $owner = staffOwner();

    actingAs(staffAdmin())
        ->put("/admin/staff/{$owner->id}/role", ['role' => 'editor'])
        ->assertForbidden();

    expect($owner->fresh()->hasRole('owner'))->toBeTrue();
});

it('deactivates another user', function () {
    $target = User::factory()->create(['is_active' => true]);
    $target->assignRole('editor');

    actingAs(staffAdmin())
        ->put("/admin/staff/{$target->id}/active", ['is_active' => false])
        ->assertRedirect();

    expect($target->fresh()->is_active)->toBeFalse();
});

it('forbids deactivating yourself', function () {
    $admin = staffAdmin();

    actingAs($admin)
        ->put("/admin/staff/{$admin->id}/active", ['is_active' => false])
        ->assertForbidden();

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('forbids deactivating the owner account', function () {
    $owner = staffOwner();

    actingAs(staffAdmin())
        ->put("/admin/staff/{$owner->id}/active", ['is_active' => false])
        ->assertForbidden();

    expect($owner->fresh()->is_active)->toBeTrue();
});

it('signs out a deactivated user on their next request', function () {
    $user = User::factory()->create(['is_active' => false]);
    $user->assignRole('admin');

    actingAs($user)->get('/dashboard')->assertRedirect('/login');
});
