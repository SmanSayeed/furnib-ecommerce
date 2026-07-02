<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\WhatsAppSettingsUpdateRequest;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * WhatsApp — one number used everywhere (floating button, product inquiry,
 * footer). Per-button toggles only show or hide that button; there is no
 * per-button number. Stored in the shared `branding` group so the public
 * settings API keeps one source of truth.
 */
class WhatsAppSettingController extends Controller
{
    private const GROUP = 'branding';

    /** The buttons the single number can appear on. */
    private const BUTTONS = ['floating', 'inquiry', 'footer'];

    public function __construct(private readonly SettingsService $settings) {}

    public function edit(): Response
    {
        $data = [
            'whatsapp' => (string) ($this->settings->get(self::GROUP, 'whatsapp') ?? ''),
        ];

        // A button shows unless it was explicitly turned off ('0').
        foreach (self::BUTTONS as $button) {
            $data["{$button}_enabled"] = $this->settings->get(self::GROUP, "whatsapp_{$button}_enabled") !== '0';
        }

        return Inertia::render('settings/whatsapp', ['whatsapp' => $data]);
    }

    public function update(WhatsAppSettingsUpdateRequest $request): RedirectResponse
    {
        $this->settings->set(self::GROUP, 'whatsapp', $request->string('whatsapp')->toString());

        foreach (self::BUTTONS as $button) {
            $this->settings->set(
                self::GROUP,
                "whatsapp_{$button}_enabled",
                $request->boolean("{$button}_enabled") ? '1' : '0',
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('WhatsApp settings updated.')]);

        return to_route('whatsapp-settings.edit');
    }
}
