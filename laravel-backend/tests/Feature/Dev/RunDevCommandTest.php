<?php

declare(strict_types=1);

use App\Actions\Dev\RunDevCommand;
use App\Support\Dev\Redactor;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->action = app(RunDevCommand::class);
});

it('runs an allow-listed safe command and captures output', function () {
    $result = $this->action->handle('config-clear');

    expect($result['id'])->toBe('config-clear')
        ->and($result['exit_code'])->toBe(0)
        ->and($result['label'])->toBe('Clear config cache');
});

it('rejects an unknown command id (no arbitrary execution)', function () {
    $this->action->handle('rm-rf-everything');
})->throws(DomainException::class);

it('blocks a destructive command without confirmation', function () {
    $this->action->handle('migrate'); // destructive, no confirm
})->throws(DomainException::class);

it('allows a destructive command when confirmed', function () {
    $result = $this->action->handle('migrate', confirmed: true);

    expect($result['id'])->toBe('migrate')
        ->and($result['exit_code'])->toBe(0);
});

it('audit-logs every command run', function () {
    $this->action->handle('config-clear');

    $log = Activity::query()->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->event)->toBe('command')
        ->and($log->properties['id'] ?? null)->toBe('config-clear');
});

it('redacts secrets from output text', function () {
    $dirty = "APP_KEY=base64:abcd1234abcd1234abcd1234abcd1234\nDB_PASSWORD: superSecret123\nAuthorization: Bearer abc.def.ghi";
    $clean = Redactor::scrub($dirty);

    expect($clean)->not->toContain('superSecret123')
        ->and($clean)->not->toContain('abcd1234abcd1234')
        ->and($clean)->not->toContain('abc.def.ghi')
        ->and($clean)->toContain('REDACTED');
});
