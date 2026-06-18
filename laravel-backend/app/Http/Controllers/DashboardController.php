<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('dashboard', [
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
