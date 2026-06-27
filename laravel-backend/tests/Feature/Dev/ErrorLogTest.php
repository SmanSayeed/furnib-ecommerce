<?php

declare(strict_types=1);

use App\Models\ErrorLog;
use App\Models\User;
use App\Support\Dev\ErrorLogger;
use Database\Seeders\PermissionRoleSeeder;
use Illuminate\Http\Request;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

/** Owner holds developer.access via the '*' wildcard. */
function errorLogOwner(): User
{
    $user = User::factory()->create();
    $user->assignRole('owner');

    return $user;
}

it('records a thrown exception to the database', function () {
    $request = Request::create('/admin/things', 'POST');

    app(ErrorLogger::class)->record(new RuntimeException('Something broke'), $request);

    $row = ErrorLog::query()->first();
    expect($row)->not->toBeNull()
        ->and($row->message)->toBe('Something broke')
        ->and($row->exception_class)->toBe(RuntimeException::class)
        ->and($row->method)->toBe('POST')
        ->and($row->url)->toContain('/admin/things')
        ->and($row->line)->toBeGreaterThan(0);
});

it('redacts secrets in the captured message', function () {
    app(ErrorLogger::class)->record(
        new RuntimeException('failed with token=abc123secret in the call'),
    );

    expect((string) ErrorLog::query()->value('message'))
        ->toContain('REDACTED')
        ->not->toContain('abc123secret');
});

it('skips database-layer exceptions to avoid recursion', function () {
    app(ErrorLogger::class)->record(new PDOException('SQLSTATE connection refused'));

    expect(ErrorLog::query()->count())->toBe(0);
});

it('lets the owner view captured errors', function () {
    ErrorLog::create([
        'level' => 'error',
        'message' => 'Boom',
        'exception_class' => RuntimeException::class,
        'file' => '/app/Foo.php',
        'line' => 10,
    ]);

    actingAs(errorLogOwner())
        ->get('/admin/dev/errors')
        ->assertOk();
});

it('blocks non-owners from the errors and logs tabs', function () {
    $user = User::factory()->create();
    $user->assignRole('admin'); // no developer.access

    actingAs($user)->get('/admin/dev/errors')->assertForbidden();
    actingAs($user)->get('/admin/dev/logs')->assertForbidden();
    actingAs($user)->delete('/admin/dev/errors')->assertForbidden();
});

it('clears all captured errors for the owner', function () {
    ErrorLog::create(['level' => 'error', 'message' => 'one']);
    ErrorLog::create(['level' => 'error', 'message' => 'two']);

    actingAs(errorLogOwner())
        ->delete('/admin/dev/errors')
        ->assertRedirect();

    expect(ErrorLog::query()->count())->toBe(0);
});

it('shows the logs tab to the owner', function () {
    actingAs(errorLogOwner())
        ->get('/admin/dev/logs')
        ->assertOk();
});
