<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Marketing\ProductFeed;
use Illuminate\Http\Response;

/**
 * Public product feed for Meta/Google Merchant. Only published, in-stock-aware
 * rows are emitted by the feed builder.
 */
class FeedController extends Controller
{
    public function products(ProductFeed $feed): Response
    {
        return response($feed->csv(), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'inline; filename="furnib-products.csv"',
        ]);
    }
}
