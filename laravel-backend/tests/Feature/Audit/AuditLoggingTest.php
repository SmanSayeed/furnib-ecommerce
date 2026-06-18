<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;
use Spatie\Activitylog\Models\Activity;

it('logs an activity with causer and request ip on an auditable update', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $subject = User::factory()->create(['name' => 'Old Name']);
    $subject->update(['name' => 'New Name']);

    $activity = Activity::query()->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($actor->id)
        ->and($activity->properties->get('ip'))->not->toBeNull();
});

it('records a system-originated change without a causer', function () {
    $subject = User::factory()->create(['name' => 'Old']);
    $subject->update(['name' => 'New']);

    $activity = Activity::query()->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBeNull();
});

it('forbids the audit log to users without audit.view', function () {
    $this->seed(PermissionRoleSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('editor');

    $this->actingAs($user)->getJson('/admin/audit-logs')->assertForbidden();
});

it('allows the audit log to the owner', function () {
    $this->seed(PermissionRoleSeeder::class);
    $owner = User::factory()->create();
    $owner->assignRole('owner');

    $this->actingAs($owner)->getJson('/admin/audit-logs')->assertOk();
});
