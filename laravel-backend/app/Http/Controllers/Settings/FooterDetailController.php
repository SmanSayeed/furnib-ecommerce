<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\FooterDetailUpdateRequest;
use App\Models\Page;
use App\Services\Settings\SettingsService;
use App\Storage\Contracts\StorageRepository;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Footer details — footer logo, contact block + footer quick links. Stored in
 * the shared `branding` group so the public settings API keeps the same shape.
 */
class FooterDetailController extends Controller
{
    private const GROUP = 'branding';

    private const TEXT_KEYS = ['contact_phone', 'contact_email', 'contact_address'];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly StorageRepository $storage,
    ) {}

    public function edit(): Response
    {
        $data = [];
        foreach (self::TEXT_KEYS as $key) {
            $data[$key] = (string) ($this->settings->get(self::GROUP, $key) ?? '');
        }

        $logo = $this->settings->get(self::GROUP, 'logo_footer');
        $data['logo_footer_url'] = is_string($logo) && $logo !== '' ? $this->storage->url($logo) : null;

        $links = $this->settings->get(self::GROUP, 'about_links');
        $data['about_links'] = is_array($links) ? array_values($links) : [];

        // CMS pages offered in the "add a page link" picker.
        $pages = Page::query()
            ->orderBy('position')
            ->orderBy('title')
            ->get(['slug', 'title'])
            ->map(fn (Page $p): array => ['slug' => $p->slug, 'title' => $p->title])
            ->all();

        return Inertia::render('settings/footer-details', [
            'footer' => $data,
            'pages' => $pages,
        ]);
    }

    public function update(FooterDetailUpdateRequest $request): RedirectResponse
    {
        foreach (self::TEXT_KEYS as $key) {
            $this->settings->set(self::GROUP, $key, $request->string($key)->toString());
        }

        // Footer logo (white/transparent PNG shown on the brand-orange footer).
        if ($request->hasFile('logo_footer')) {
            $old = $this->settings->get(self::GROUP, 'logo_footer');
            $path = $this->storage->store($request->file('logo_footer'), self::GROUP);
            $this->settings->set(self::GROUP, 'logo_footer', $path);

            if (is_string($old) && $old !== '' && $old !== $path) {
                $this->storage->delete($old);
            }
        }

        // Re-keyed to a clean list; an empty submission clears saved links.
        /** @var array<int, array{label:string, url:string}> $links */
        $links = $request->validated('about_links') ?? [];
        $this->settings->set(self::GROUP, 'about_links', array_values($links));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Footer details updated.')]);

        return to_route('footer-details.edit');
    }
}
