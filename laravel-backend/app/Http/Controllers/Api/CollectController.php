<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CollectEventRequest;
use App\Models\Product;
use App\Support\Capi\CapiEvents;
use App\Support\Capi\CapiUserData;
use App\Support\Capi\ConversionApi;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Server-side tagging endpoint: the storefront posts funnel events here and we
 * forward them to the Meta Conversions API. The browser sends the SAME event_id
 * to the Pixel so Meta de-duplicates. Monetary value is derived server-side from
 * the published product (never trusted from the client). Failures are swallowed
 * so a marketing hiccup never disturbs the shopper.
 */
class CollectController extends Controller
{
    public function __construct(private readonly ConversionApi $capi) {}

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

        $user = new CapiUserData(
            ip: $request->ip(),
            userAgent: (string) $request->userAgent(),
            fbp: ($data['fbp'] ?? null) ?: $cookie('_fbp'),
            fbc: ($data['fbc'] ?? null) ?: $cookie('_fbc'),
        );

        $qty = (int) ($data['qty'] ?? 1);
        $url = $data['event_source_url'] ?? $request->header('referer');

        $event = match ((string) $data['event']) {
            'ViewContent' => CapiEvents::viewContent($product, $qty, $user, $data['event_id'], $url),
            'InitiateCheckout' => CapiEvents::initiateCheckout($product, $qty, $user, $data['event_id'], $url),
            default => CapiEvents::lead($product, $qty, $user, $data['event_id'], $url), // 'Lead' (validated set)
        };

        try {
            $this->capi->send($event);
        } catch (Throwable) {
            // Non-fatal: never surface a marketing failure to the storefront.
        }

        return response()->json(['recorded' => true]);
    }
}
