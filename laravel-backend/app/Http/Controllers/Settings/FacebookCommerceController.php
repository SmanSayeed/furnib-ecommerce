<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\FacebookCommerceUpdateRequest;
use App\Models\Category;
use App\Services\Marketing\ProductFeed;
use App\Services\Settings\SettingsService;
use App\Support\Marketing\FeedAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Marketing → Facebook Commerce. The BD scope is a SCHEDULED FEED (Meta pulls the
 * CSV hourly) — native checkout is unavailable in Bangladesh, so there is no
 * Shops order sync. This page manages the secured feed URL + credentials and a
 * category-filtered CSV export. The feed password is stored encrypted and shown
 * in plaintext exactly once, when it is generated or regenerated.
 */
class FacebookCommerceController extends Controller
{
    private const GROUP = FeedAccess::GROUP;

    public function __construct(
        private readonly SettingsService $settings,
        private readonly FeedAccess $feed,
    ) {}

    public function edit(Request $request): InertiaResponse
    {
        return Inertia::render('settings/facebook-commerce', [
            'feed' => [
                'enabled' => $this->feed->enabled(),
                'url' => $this->feed->url(),
                'username' => $this->feed->username(),
                'password_set' => $this->feed->password() !== null,
                'catalog_id' => $this->settings->get(self::GROUP, 'catalog_id'),
                'business_id' => $this->settings->get(self::GROUP, 'business_id'),
            ],
            // A freshly generated password, shown once via the flash bag.
            'newFeedPassword' => $request->session()->get('new_feed_password'),
            'categories' => Category::query()->orderBy('title')->get(['id', 'title'])
                ->map(fn (Category $c): array => ['id' => $c->id, 'title' => $c->title])
                ->all(),
        ]);
    }

    public function update(FacebookCommerceUpdateRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->settings->set(self::GROUP, 'catalog_id', $data['catalog_id'] ?? null);
        $this->settings->set(self::GROUP, 'business_id', $data['business_id'] ?? null);

        if (filled($data['feed_username'] ?? null)) {
            $this->settings->set(self::GROUP, 'feed_username', $data['feed_username']);
        }

        $enabled = (bool) ($data['feed_enabled'] ?? false);
        $this->settings->set(self::GROUP, 'feed_enabled', $enabled);

        $fresh = null;
        if ($enabled) {
            // Turning the feed on mints the slug/username/password if absent.
            $fresh = $this->feed->ensureCredentials();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Facebook Commerce settings saved.')]);

        $redirect = back();

        return $fresh !== null ? $redirect->with('new_feed_password', $fresh) : $redirect;
    }

    /**
     * Rotate the feed slug + password, invalidating the old URL. The new password
     * is flashed once for the admin to copy into Commerce Manager.
     */
    public function regenerate(): RedirectResponse
    {
        $password = $this->feed->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Feed URL and password regenerated.')]);

        return back()->with('new_feed_password', $password);
    }

    /**
     * Direct CSV download for the admin (authenticated by the page's
     * marketing.manage gate), optionally filtered to selected categories.
     */
    public function download(Request $request, ProductFeed $feed): Response
    {
        $categoryIds = array_values(array_filter(array_map(
            'intval',
            (array) $request->query('category_ids', []),
        )));

        return response($feed->csv($categoryIds === [] ? null : $categoryIds), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="furnib-products.csv"',
        ]);
    }
}
