<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Marketing\ProductFeed;
use App\Support\Marketing\FeedAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Product feed for Meta/Google Merchant. NOT public: the catalogue with prices
 * and stock is gated behind an on/off switch, an unguessable path segment, and
 * HTTP Basic auth (see FeedAccess). Meta's scheduled-feed fetcher authenticates
 * with the Basic credentials the owner sets in Marketing → Facebook Commerce.
 */
class FeedController extends Controller
{
    public function products(string $token, Request $request, FeedAccess $access, ProductFeed $feed): Response
    {
        // Switched off, or a path token that doesn't match → indistinguishable
        // 404, so the endpoint's existence isn't confirmed to a scanner.
        if (! $access->enabled() || $access->slug() === null || ! hash_equals((string) $access->slug(), $token)) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        // Basic auth — challenge when missing or wrong.
        if (! $access->verify($token, $request->getUser(), $request->getPassword())) {
            return response('Authentication required.', HttpResponse::HTTP_UNAUTHORIZED, [
                'WWW-Authenticate' => 'Basic realm="Furnib product feed"',
            ]);
        }

        return response($feed->csv(), HttpResponse::HTTP_OK, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'inline; filename="furnib-products.csv"',
        ]);
    }
}
