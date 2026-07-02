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

    private const TEXT_KEYS = [
        'contact_phone', 'contact_email', 'contact_address', 'contact_hours',
        // Payment-gateway compliance fields.
        'trade_license_no', 'registered_address',
        'delivery_inside_dhaka', 'delivery_outside_dhaka',
        // Trust-badge headings + optional links.
        'member_of_heading', 'member_of_url',
        'delivery_partner_heading', 'delivery_partner_url',
    ];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly StorageRepository $storage,
    ) {}

    public function edit(): Response
    {
        // Sensible defaults for the delivery-timeline compliance copy (#5).
        $defaults = [
            'delivery_inside_dhaka' => 'Inside Dhaka: 5 days',
            'delivery_outside_dhaka' => 'Outside Dhaka: 10 days',
            'member_of_heading' => "Member's Of",
            'delivery_partner_heading' => 'Delivery Partner',
        ];

        $data = [];
        foreach (self::TEXT_KEYS as $key) {
            $stored = $this->settings->get(self::GROUP, $key);
            $data[$key] = is_string($stored) && $stored !== ''
                ? $stored
                : ($defaults[$key] ?? '');
        }

        $logo = $this->settings->get(self::GROUP, 'logo_footer');
        $data['logo_footer_url'] = is_string($logo) && $logo !== '' ? $this->storage->url($logo) : null;

        $paymentBanner = $this->settings->get(self::GROUP, 'payment_banner');
        $data['payment_banner_url'] = is_string($paymentBanner) && $paymentBanner !== ''
            ? $this->storage->url($paymentBanner)
            : null;

        // Trust-badge toggles (stored as '1'/'0'; default off).
        $data['member_of_enabled'] = $this->settings->get(self::GROUP, 'member_of_enabled') === '1';
        $data['delivery_partner_enabled'] = $this->settings->get(self::GROUP, 'delivery_partner_enabled') === '1';

        // Trust-badge images.
        $memberImage = $this->settings->get(self::GROUP, 'member_of_image');
        $data['member_of_image_url'] = is_string($memberImage) && $memberImage !== ''
            ? $this->storage->url($memberImage)
            : null;

        $deliveryImage = $this->settings->get(self::GROUP, 'delivery_partner_image');
        $data['delivery_partner_image_url'] = is_string($deliveryImage) && $deliveryImage !== ''
            ? $this->storage->url($deliveryImage)
            : null;

        // Published CMS pages + their footer visibility. The admin toggles each
        // page in/out of the storefront footer here; system (legal) pages are
        // always shown and cannot be hidden (gateway compliance).
        $pages = Page::query()
            ->published()
            ->orderBy('position')
            ->orderBy('title')
            ->get(['id', 'slug', 'title', 'is_system', 'show_in_footer'])
            ->map(fn (Page $p): array => [
                'id' => $p->id,
                'slug' => $p->slug,
                'title' => $p->title,
                'is_system' => $p->is_system,
                'show_in_footer' => $p->show_in_footer,
            ])
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

        // Gateway "payment methods" banner (compliance #8).
        if ($request->hasFile('payment_banner')) {
            $old = $this->settings->get(self::GROUP, 'payment_banner');
            $path = $this->storage->store($request->file('payment_banner'), self::GROUP);
            $this->settings->set(self::GROUP, 'payment_banner', $path);

            if (is_string($old) && $old !== '' && $old !== $path) {
                $this->storage->delete($old);
            }
        }

        // Trust-badge toggles — stored as '1'/'0' like the social flags.
        $this->settings->set(self::GROUP, 'member_of_enabled', $request->boolean('member_of_enabled') ? '1' : '0');
        $this->settings->set(self::GROUP, 'delivery_partner_enabled', $request->boolean('delivery_partner_enabled') ? '1' : '0');

        // "Member's Of" badge image.
        if ($request->hasFile('member_of_image')) {
            $old = $this->settings->get(self::GROUP, 'member_of_image');
            $path = $this->storage->store($request->file('member_of_image'), self::GROUP);
            $this->settings->set(self::GROUP, 'member_of_image', $path);

            if (is_string($old) && $old !== '' && $old !== $path) {
                $this->storage->delete($old);
            }
        }

        // "Delivery Partner" badge image.
        if ($request->hasFile('delivery_partner_image')) {
            $old = $this->settings->get(self::GROUP, 'delivery_partner_image');
            $path = $this->storage->store($request->file('delivery_partner_image'), self::GROUP);
            $this->settings->set(self::GROUP, 'delivery_partner_image', $path);

            if (is_string($old) && $old !== '' && $old !== $path) {
                $this->storage->delete($old);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Footer details updated.')]);

        return to_route('footer-details.edit');
    }

    /**
     * Toggle whether a published page shows in the storefront footer. System
     * (legal) pages must always be present for payment-gateway compliance, so
     * they can never be hidden.
     */
    public function togglePage(Page $page): RedirectResponse
    {
        if ($page->is_system && $page->show_in_footer) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Legal pages must stay in the footer.')]);

            return to_route('footer-details.edit');
        }

        $page->update(['show_in_footer' => ! $page->show_in_footer]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $page->show_in_footer
                ? __('Page added to the footer.')
                : __('Page removed from the footer.'),
        ]);

        return to_route('footer-details.edit');
    }
}
