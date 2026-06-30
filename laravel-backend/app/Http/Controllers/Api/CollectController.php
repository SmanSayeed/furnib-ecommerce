<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CollectEventRequest;
use App\Models\Product;
use App\Support\Capi\CapiEvents;
use App\Support\Capi\CapiUserData;
use App\Support\Capi\ConversionApi;
use App\Support\Tiktok\EventsApi;
use App\Support\Tiktok\TiktokEvents;
use App\Support\Tiktok\TiktokUserData;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Server-side tagging endpoint: the storefront posts funnel events here and we
 * forward them to BOTH the Meta Conversions API and the TikTok Events API. The
 * browser sends the SAME event_id to each pixel so both platforms de-duplicate.
 * Monetary value is derived server-side from the published product (never
 * trusted from the client). Failures are swallowed so a marketing hiccup never
 * disturbs the shopper.
 */
class CollectController extends Controller
{
    public function __construct(
        private readonly ConversionApi $capi,
        private readonly EventsApi $tiktok,
    ) {}

    public function store(CollectEventRequest $request): JsonResponse
    {
        $data = $request->validated();

        $product = isset($data['sku']) && $data['sku'] !== ''
            ? Product::query()->published()->where('sku', $data['sku'])->first()
            : null;

        // A product-scoped event with an unknown/unpublished sku is ignored.
        if ($product === null) {
            return response()->json(['recorded' => false]);
        }

        $cookie = static fn (string $name): ?string => is_string($v = $request->cookie($name)) ? $v : null;

        $ip = $request->ip();
        $userAgent = (string) $request->userAgent();
        $event = (string) $data['event'];
        $eventId = (string) $data['event_id'];
        $qty = (int) ($data['qty'] ?? 1);
        $url = $data['event_source_url'] ?? $request->header('referer');

        // 1) Meta Conversions API.
        $metaUser = new CapiUserData(
            ip: $ip,
            userAgent: $userAgent,
            fbp: ($data['fbp'] ?? null) ?: $cookie('_fbp'),
            fbc: ($data['fbc'] ?? null) ?: $cookie('_fbc'),
        );

        $metaEvent = match ($event) {
            'ViewContent' => CapiEvents::viewContent($product, $qty, $metaUser, $eventId, $url),
            'InitiateCheckout' => CapiEvents::initiateCheckout($product, $qty, $metaUser, $eventId, $url),
            default => CapiEvents::lead($product, $qty, $metaUser, $eventId, $url), // 'Lead' (validated set)
        };

        try {
            $this->capi->send($metaEvent);
        } catch (Throwable) {
            // Non-fatal: never surface a marketing failure to the storefront.
        }

        // 2) TikTok Events API (same event_id → de-duplicated with the pixel).
        $ttUser = new TiktokUserData(
            ip: $ip,
            userAgent: $userAgent,
            ttp: ($data['ttp'] ?? null) ?: $cookie('_ttp'),
            ttclid: ($data['ttclid'] ?? null) ?: $cookie('ttclid'),
        );

        try {
            $this->tiktok->send(TiktokEvents::product($event, $product, $qty, $ttUser, $eventId, $url));
        } catch (Throwable) {
            // Non-fatal.
        }

        return response()->json(['recorded' => true]);
    }
}
