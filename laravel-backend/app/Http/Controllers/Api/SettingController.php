<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Settings\SettingsService;
use App\Storage\Contracts\StorageRepository;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly StorageRepository $storage,
    ) {}

    /**
     * Public, non-secret branding settings for the storefront.
     */
    public function index(): JsonResponse
    {
        $b = $this->settings->toArray('branding');

        return response()->json([
            'data' => [
                'site_name' => $b['site_name'] ?? null,
                'tagline' => $b['tagline'] ?? null,
                'whatsapp' => $b['whatsapp'] ?? null,
                'contact' => [
                    'phone' => $b['contact_phone'] ?? null,
                    'email' => $b['contact_email'] ?? null,
                    'address' => $b['contact_address'] ?? null,
                ],
                'logo_light' => $this->url($b['logo_light'] ?? null),
                'logo_dark' => $this->url($b['logo_dark'] ?? null),
                'logo_footer' => $this->url($b['logo_footer'] ?? null),
                'favicon' => $this->url($b['favicon'] ?? null),
                'banners' => $this->banners($b),
                'socials' => $this->socials($b),
                // Every published page marked "show in footer", in display order.
                // The storefront footer "About Us" column renders these links
                // automatically (→ /p/{slug}); no manual link setup needed.
                'footer_pages' => $this->footerPages(),
                // Payment-gateway compliance data (non-secret). Shown on the
                // storefront footer / policy pages.
                'compliance' => [
                    'trade_license_no' => $b['trade_license_no'] ?? null,
                    'registered_address' => $b['registered_address'] ?? null,
                    'delivery_inside_dhaka' => $b['delivery_inside_dhaka'] ?? 'Inside Dhaka: 5 days',
                    'delivery_outside_dhaka' => $b['delivery_outside_dhaka'] ?? 'Outside Dhaka: 10 days',
                    'payment_banner_url' => $this->url($b['payment_banner'] ?? null),
                ],
                // Footer "opening hours" line.
                'footer_contact' => [
                    'hours' => $b['contact_hours'] ?? null,
                ],
                // Trust badges: "Member's Of" + "Delivery Partner".
                'footer_badges' => [
                    'member_of' => [
                        'enabled' => ($b['member_of_enabled'] ?? '0') === '1',
                        'heading' => $b['member_of_heading'] ?? "Member's Of",
                        'image_url' => $this->url($b['member_of_image'] ?? null),
                        'url' => $b['member_of_url'] ?? null,
                    ],
                    'delivery_partner' => [
                        'enabled' => ($b['delivery_partner_enabled'] ?? '0') === '1',
                        'heading' => $b['delivery_partner_heading'] ?? 'Delivery Partner',
                        'image_url' => $this->url($b['delivery_partner_image'] ?? null),
                        'url' => $b['delivery_partner_url'] ?? null,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Published, footer-visible pages for the storefront footer "About Us"
     * column, in display order — both system (legal) pages and any custom
     * published page the admin left visible.
     *
     * @return array<int, array{slug: string, title: string}>
     */
    private function footerPages(): array
    {
        return Page::query()
            ->inFooter()
            ->get(['slug', 'title'])
            ->map(fn (Page $p): array => ['slug' => $p->slug, 'title' => $p->title])
            ->all();
    }

    /**
     * Enabled, non-empty "Follow us" links keyed by platform.
     *
     * A platform shows unless explicitly disabled (flag === '0'), so existing
     * links stay visible without needing the toggle re-saved.
     *
     * @param  array<string, mixed>  $b
     * @return array<string, string>
     */
    private function socials(array $b): array
    {
        $platforms = ['facebook', 'instagram', 'youtube', 'linkedin', 'x', 'pinterest', 'tiktok'];
        $out = [];

        foreach ($platforms as $platform) {
            $url = $b["social_{$platform}"] ?? null;
            $enabled = ($b["social_{$platform}_enabled"] ?? '1') !== '0';

            if (is_string($url) && $url !== '' && $enabled) {
                $out[$platform] = $url;
            }
        }

        return $out;
    }

    /**
     * Responsive home banners, one object per non-empty slot in order.
     *
     * Each slot resolves to `{ desktop, mobile }` where:
     *   desktop = banner_N_desktop ?? banner_N (legacy)
     *   mobile  = banner_N_mobile  ?? desktop
     * A slot is skipped only when it has no desktop, mobile, or legacy image.
     *
     * @param  array<string, mixed>  $b
     * @return array<int, array{desktop: ?string, mobile: ?string}>
     */
    private function banners(array $b): array
    {
        $out = [];

        foreach ([1, 2] as $n) {
            $desktop = $this->url($b["banner_{$n}_desktop"] ?? null)
                ?? $this->url($b["banner_{$n}"] ?? null);
            $mobile = $this->url($b["banner_{$n}_mobile"] ?? null) ?? $desktop;

            if ($desktop === null && $mobile === null) {
                continue;
            }

            $out[] = ['desktop' => $desktop, 'mobile' => $mobile];
        }

        return $out;
    }

    private function url(mixed $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $this->storage->url($path);
    }
}
