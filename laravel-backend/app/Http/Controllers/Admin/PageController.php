<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageFormRequest;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Mews\Purifier\Facades\Purifier;

class PageController extends Controller
{
    public function index(): Response
    {
        $pages = Page::query()
            ->orderBy('position')
            ->orderBy('title')
            ->get(['id', 'slug', 'title', 'is_published', 'is_system', 'show_in_footer', 'position'])
            ->all();

        return Inertia::render('pages/index', ['pages' => $pages]);
    }

    public function create(): Response
    {
        return Inertia::render('pages/form', ['page' => null]);
    }

    public function store(PageFormRequest $request): RedirectResponse
    {
        Page::create($this->payload($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page created.')]);

        return to_route('admin.pages.index');
    }

    public function edit(Page $page): Response
    {
        return Inertia::render('pages/form', [
            'page' => [
                'id' => $page->id,
                'slug' => $page->slug,
                'title' => $page->title,
                'body' => $page->body_html,
                'is_published' => $page->is_published,
                'position' => $page->position,
            ],
        ]);
    }

    public function update(PageFormRequest $request, Page $page): RedirectResponse
    {
        $page->update($this->payload($request, $page));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page updated.')]);

        return to_route('admin.pages.index');
    }

    public function destroy(Page $page): RedirectResponse
    {
        // System pages (legal / compliance) are protected and cannot be deleted.
        if ($page->is_system) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('System pages cannot be deleted.')]);

            return to_route('admin.pages.index');
        }

        $page->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page deleted.')]);

        return to_route('admin.pages.index');
    }

    /**
     * Validated attributes with a derived slug and HTMLPurifier-sanitised body.
     *
     * Sanitising here (never trusting the editor's HTML) is the XSS guard: the
     * stored body_html is rendered verbatim in the storefront.
     *
     * @return array<string, mixed>
     */
    private function payload(PageFormRequest $request, ?Page $page = null): array
    {
        $title = (string) $request->validated('title');

        if ($page !== null && $page->is_system) {
            // System pages keep a fixed slug (footer links depend on it);
            // title / body stay editable.
            $slug = $page->slug;
        } else {
            $slug = (string) ($request->validated('slug') ?: Str::slug($title));

            // Guarantee uniqueness when the title-derived slug collides.
            $slug = $this->uniqueSlug($slug, $page?->id);
        }

        $body = $request->validated('body');
        $clean = is_string($body) && $body !== '' ? Purifier::clean($body) : null;

        return [
            'title' => $title,
            'slug' => $slug,
            'body_html' => $clean,
            'is_published' => $request->boolean('is_published'),
            'position' => (int) ($request->validated('position') ?? 0),
        ];
    }

    private function uniqueSlug(string $slug, ?int $ignoreId): string
    {
        $base = $slug !== '' ? $slug : 'page';
        $candidate = $base;
        $n = 1;

        while (Page::query()
            ->where('slug', $candidate)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $candidate = $base.'-'.(++$n);
        }

        return $candidate;
    }
}
