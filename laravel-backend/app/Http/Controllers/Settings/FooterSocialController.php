<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\FooterSocialUpdateRequest;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Follow us" footer social links — url + show/hide flag per platform. Stored in
 * the shared `branding` group so the public settings API keeps the same shape.
 */
class FooterSocialController extends Controller
{
    private const GROUP = 'branding';

    private const PLATFORMS = ['facebook', 'instagram', 'youtube', 'linkedin', 'x', 'pinterest', 'tiktok'];

    public function __construct(private readonly SettingsService $settings) {}

    public function edit(): Response
    {
        $data = [];
        foreach (self::PLATFORMS as $platform) {
            $data["social_{$platform}"] = (string) ($this->settings->get(self::GROUP, "social_{$platform}") ?? '');
            // Defaults to enabled until explicitly toggled off.
            $data["social_{$platform}_enabled"] = $this->settings->get(self::GROUP, "social_{$platform}_enabled") !== '0';
        }

        return Inertia::render('settings/footer-social', ['social' => $data]);
    }

    public function update(FooterSocialUpdateRequest $request): RedirectResponse
    {
        foreach (self::PLATFORMS as $platform) {
            $this->settings->set(self::GROUP, "social_{$platform}", $request->string("social_{$platform}")->toString());
            $this->settings->set(self::GROUP, "social_{$platform}_enabled", $request->boolean("social_{$platform}_enabled") ? '1' : '0');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Follow-us links updated.')]);

        return to_route('footer-social.edit');
    }
}
