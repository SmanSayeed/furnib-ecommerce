<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\Analytics\DashboardMetrics;
use App\Support\Lists\DateRange;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardMetrics $metrics) {}

    public function index(Request $request): Response
    {
        // Order/revenue KPIs are windowed (default: this month). Catalog KPIs
        // below stay all-time.
        $preset = (string) $request->query('range', 'this_month');
        if (! in_array($preset, DateRange::PRESETS, true) || $preset === 'all') {
            $preset = 'this_month';
        }
        $from = $request->query('from');
        $to = $request->query('to');
        $range = DateRange::fromPreset(
            $preset,
            is_string($from) ? $from : null,
            is_string($to) ? $to : null,
        );

        $summary = $this->metrics->summary($range);

        return Inertia::render('dashboard', [
            'window' => [
                'range' => $preset,
                'from' => (string) $request->query('from', ''),
                'to' => (string) $request->query('to', ''),
            ],
            'orderStats' => [
                'orders' => $summary['orders'],
                'revenue' => Money::fromMinor($summary['revenue_minor'])->format(),
                'advance_collected' => Money::fromMinor($summary['advance_minor'])->format(),
                'new_customers' => $summary['new_customers'],
                'aov' => Money::fromMinor($summary['aov_minor'])->format(),
            ],
            'series' => $this->metrics->dailySeries($range),
            'stats' => [
                'products' => Product::query()->count(),
                'published' => Product::query()->where('product_status', 'published')->count(),
                'categories' => Category::query()->count(),
                'lowStock' => Product::query()->where('stock_status', true)->where('stock_amount', '<=', 5)->count(),
            ],
            'byCategory' => Category::query()
                ->withCount('products')
                ->orderBy('position_order')
                ->get()
                ->map(fn (Category $c): array => [
                    'name' => $c->title,
                    'products' => (int) $c->getAttribute('products_count'),
                ])
                ->values()
                ->all(),
            'recentProducts' => Product::query()
                ->with('category:id,title')
                ->latest()
                ->take(6)
                ->get()
                ->map(fn (Product $p): array => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'sku' => $p->sku,
                    'category' => $p->category?->title,
                    'status' => $p->product_status,
                    'stock' => $p->stock_amount,
                    'price' => $p->price->format(),
                ])
                ->values()
                ->all(),
        ]);
    }
}
