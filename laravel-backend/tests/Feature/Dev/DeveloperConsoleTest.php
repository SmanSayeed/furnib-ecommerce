<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

function devOwner(): User
{
    $user = User::factory()->create();
    $user->assignRole('owner'); // has developer.access via '*'

    return $user;
}

it('lets the owner view the developer console', function () {
    actingAs(devOwner())
        ->get('/admin/dev')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dev/index')
            ->has('commands')
            ->has('system')
            ->has('health'));
});

it('forbids a non-owner admin from the developer console', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin'); // no developer.access

    actingAs($admin)->get('/admin/dev')->assertForbidden();
    actingAs($admin)->post('/admin/dev/run', ['id' => 'config-clear'])->assertForbidden();
});

it('runs an allow-listed command for the owner', function () {
    actingAs(devOwner())
        ->post('/admin/dev/run', ['id' => 'config-clear'])
        ->assertRedirect();
});

it('rejects a destructive command without confirmation', function () {
    actingAs(devOwner())
        ->from('/admin/dev')
        ->post('/admin/dev/run', ['id' => 'migrate', 'confirmed' => false])
        ->assertRedirect('/admin/dev'); // bounced back with an error toast, no crash
});

it('rejects an unknown command id', function () {
    actingAs(devOwner())
        ->from('/admin/dev')
        ->post('/admin/dev/run', ['id' => 'rm-rf'])
        ->assertRedirect('/admin/dev');
});
