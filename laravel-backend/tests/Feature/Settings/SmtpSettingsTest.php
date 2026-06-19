<?php

declare(strict_types=1);

use App\Mail\TestMail;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\Mail\MailConfigurator;
use Database\Seeders\PermissionRoleSeeder;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionRoleSeeder::class);
});

function settingsManager(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin'); // settings.manage

    return $user;
}

it('saves SMTP settings with the password encrypted at rest and masked on read', function () {
    actingAs(settingsManager())->post('/settings/smtp', [
        'host' => 'smtp.mailtrap.io',
        'port' => 587,
        'username' => 'furnib',
        'password' => 'super-secret-pass',
        'encryption' => 'tls',
        'from_address' => 'shop@furnib.test',
        'from_name' => 'Furnib',
    ])->assertRedirect();

    // Stored ciphertext is not the plaintext, and the row is flagged secret.
    $row = Setting::query()->where('group', 'smtp')->where('key', 'password')->firstOrFail();
    expect($row->is_secret)->toBeTrue()
        ->and($row->value)->not->toBe('super-secret-pass');

    // Server-side decrypt round-trips; the public payload masks it.
    $settings = app(SettingsService::class);
    expect($settings->get('smtp', 'password'))->toBe('super-secret-pass')
        ->and($settings->toArray('smtp'))->toMatchArray(['password' => null]);
});

it('does not overwrite the stored password when left blank', function () {
    $settings = app(SettingsService::class);
    $settings->set('smtp', 'password', 'original-pass', isSecret: true);

    actingAs(settingsManager())->post('/settings/smtp', [
        'host' => 'smtp.mailtrap.io',
        'port' => 587,
    ])->assertRedirect();

    expect($settings->get('smtp', 'password'))->toBe('original-pass');
});

it('forbids users without settings.manage', function () {
    $user = User::factory()->create();
    $user->assignRole('sub-admin'); // no settings.manage

    actingAs($user)->post('/settings/smtp', ['host' => 'x', 'port' => 25])
        ->assertForbidden();
});

it('sends a test email through the configured transport', function () {
    Mail::fake();

    actingAs(settingsManager())->post('/settings/smtp/test', ['email' => 'me@furnib.test'])
        ->assertRedirect();

    Mail::assertSent(TestMail::class, fn (TestMail $mail) => $mail->hasTo('me@furnib.test'));
});

it('applies the stored SMTP settings onto the mail config', function () {
    $settings = app(SettingsService::class);
    $settings->set('smtp', 'host', 'smtp.example.com');
    $settings->set('smtp', 'port', 2525);
    $settings->set('smtp', 'username', 'user');
    $settings->set('smtp', 'password', 'pw', isSecret: true);
    $settings->set('smtp', 'from_address', 'shop@furnib.test');

    expect(app(MailConfigurator::class)->apply())->toBeTrue();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.example.com')
        ->and(config('mail.mailers.smtp.port'))->toBe(2525)
        ->and(config('mail.mailers.smtp.password'))->toBe('pw')
        ->and(config('mail.from.address'))->toBe('shop@furnib.test');
});

it('reports false when SMTP is not configured', function () {
    expect(app(MailConfigurator::class)->apply())->toBeFalse();
});
