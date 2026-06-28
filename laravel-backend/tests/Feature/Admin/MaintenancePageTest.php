<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

it('shows the maintenance page to the owner', function () {
    $owner = User::factory()->create();
    $owner->assignRole('owner');

    actingAs($owner)->get('/admin/maintenance')->assertOk();
});

it('blocks the maintenance page for non-owners', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin'); // no maintenance.manage

    actingAs($admin)->get('/admin/maintenance')->assertForbidden();
});

it('persists the maintenance toggle from the page', function () {
    $owner = User::factory()->create();
    $owner->assignRole('owner');

    actingAs($owner)
        ->put('/admin/maintenance', ['enabled' => true, 'message' => 'Back soon'])
        ->assertRedirect();

    expect(Setting::where('group', 'maintenance')->where('key', 'enabled')->value('value'))
        ->not->toBeNull();
});
