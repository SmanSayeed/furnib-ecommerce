<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\JsonResponse;

class PageController extends Controller
{
    /**
     * Published pages for the storefront footer (slug + title only).
     */
    public function index(): JsonResponse
    {
        $pages = Page::query()
            ->published()
            ->orderBy('position')
            ->orderBy('title')
            ->get(['slug', 'title'])
            ->all();

        return response()->json(['data' => $pages]);
    }

    /**
     * A single published page with its sanitised HTML body.
     */
    public function show(string $slug): JsonResponse
    {
        $page = Page::query()->published()->where('slug', $slug)->first();

        if ($page === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json([
            'data' => [
                'slug' => $page->slug,
                'title' => $page->title,
                'body_html' => $page->body_html,
            ],
        ]);
    }
}
