<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PermissionRoleSeeder;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

function owner(): User
{
    $user = User::factory()->create();
    $user->assignRole('owner');

    return $user;
}

it('lets the owner enable maintenance and exposes the flag publicly', function () {
    actingAs(owner())->put('/admin/maintenance', ['enabled' => true, 'message' => 'Back soon'])
        ->assertRedirect();

    $this->getJson('/api/v1/maintenance')
        ->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.message', 'Back soon');
});

it('is reversible — disabling restores normal operation', function () {
    actingAs(owner())->put('/admin/maintenance', ['enabled' => true])->assertRedirect();
    actingAs(owner())->put('/admin/maintenance', ['enabled' => false])->assertRedirect();

    $this->getJson('/api/v1/maintenance')->assertJsonPath('data.enabled', false);
});

it('audit-logs every maintenance toggle', function () {
    actingAs(owner())->put('/admin/maintenance', ['enabled' => true])->assertRedirect();

    $activity = Activity::query()->where('log_name', 'Maintenance')->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->event)->toBe('enabled')
        ->and($activity->properties->get('enabled'))->toBeTrue();
});

it('forbids non-owners (no maintenance.manage) from toggling', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin'); // has settings.manage but NOT maintenance.manage

    actingAs($admin)->put('/admin/maintenance', ['enabled' => true])->assertForbidden();
});

it('defaults to disabled on the public endpoint', function () {
    $this->getJson('/api/v1/maintenance')
        ->assertOk()
        ->assertJsonPath('data.enabled', false);
});

it('never contains a filesystem-deletion call (no destructive backdoor)', function () {
    $sources = [
        file_get_contents(app_path('Http/Controllers/Admin/MaintenanceController.php')),
        file_get_contents(app_path('Http/Controllers/Api/MaintenanceController.php')),
    ];

    $forbidden = ['unlink(', 'rmdir(', 'deleteDirectory', 'Storage::delete', 'File::delete', '->delete('];

    foreach ($sources as $source) {
        foreach ($forbidden as $needle) {
            expect(str_contains((string) $source, $needle))->toBeFalse();
        }
    }
});
