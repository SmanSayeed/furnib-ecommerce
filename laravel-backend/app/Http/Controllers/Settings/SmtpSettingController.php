<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Mail\SendTestEmail;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SmtpSettingsUpdateRequest;
use App\Services\Settings\SettingsService;
use App\Support\Mail\MailConfigurator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin editor for the dynamic SMTP transport. The password is stored encrypted
 * and never returned to the client (masked in the edit payload).
 */
class SmtpSettingController extends Controller
{
    private const GROUP = MailConfigurator::GROUP;

    /** Non-secret text fields. */
    private const TEXT_KEYS = ['host', 'username', 'encryption', 'from_address', 'from_name'];

    public function __construct(private readonly SettingsService $settings) {}

    public function edit(): Response
    {
        // toArray() masks the secret password (returns null for it).
        $smtp = $this->settings->toArray(self::GROUP);
        $smtp['password_set'] = filled($this->settings->get(self::GROUP, 'password'));

        return Inertia::render('settings/smtp', [
            'smtp' => $smtp,
        ]);
    }

    public function update(SmtpSettingsUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        foreach (self::TEXT_KEYS as $key) {
            $this->settings->set(self::GROUP, $key, $validated[$key] ?? null);
        }

        $this->settings->set(self::GROUP, 'port', (int) $validated['port']);

        // Password is write-only: only overwrite when a new one is supplied.
        if (filled($validated['password'] ?? null)) {
            $this->settings->set(self::GROUP, 'password', $validated['password'], isSecret: true);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('SMTP settings saved.')]);

        return back();
    }

    public function test(Request $request, SendTestEmail $sendTestEmail): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $sendTestEmail->handle($data['email']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test email sent.')]);

        return back();
    }
}
