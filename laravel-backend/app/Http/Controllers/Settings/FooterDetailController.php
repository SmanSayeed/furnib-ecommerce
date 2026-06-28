<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\FooterDetailUpdateRequest;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Footer details — contact block + footer quick links. Stored in the shared
 * `branding` group so the public settings API keeps the same shape.
 */
class FooterDetailController extends Controller
{
    private const GROUP = 'branding';

    private const TEXT_KEYS = ['contact_phone', 'contact_email', 'contact_address'];

    public function __construct(private readonly SettingsService $settings) {}

    public function edit(): Response
    {
        $data = [];
        foreach (self::TEXT_KEYS as $key) {
            $data[$key] = (string) ($this->settings->get(self::GROUP, $key) ?? '');
        }

        $links = $this->settings->get(self::GROUP, 'about_links');
        $data['about_links'] = is_array($links) ? array_values($links) : [];

        return Inertia::render('settings/footer-details', ['footer' => $data]);
    }

    public function update(FooterDetailUpdateRequest $request): RedirectResponse
    {
        foreach (self::TEXT_KEYS as $key) {
            $this->settings->set(self::GROUP, $key, $request->string($key)->toString());
        }

        // Re-keyed to a clean list; an empty submission clears saved links.
        /** @var array<int, array{label:string, url:string}> $links */
        $links = $request->validated('about_links') ?? [];
        $this->settings->set(self::GROUP, 'about_links', array_values($links));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Footer details updated.')]);

        return to_route('footer-details.edit');
    }
}
