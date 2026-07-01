<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryFormRequest;
use App\Models\Category;
use App\Services\Catalog\CategoryService;
use App\Storage\Contracts\StorageRepository;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoryUiController extends Controller
{
    private const IMAGE_KEYS = ['header_image', 'header_image_mobile', 'thumbnail_image'];

    public function __construct(
        private readonly CategoryService $service,
        private readonly StorageRepository $storage,
    ) {}

    public function index(): Response
    {
        $categories = Category::query()
            ->withCount('products')
            ->orderBy('position_order')
            ->orderBy('title')
            ->get()
            ->map(fn (Category $c): array => [
                'id' => $c->id,
                'title' => $c->title,
                'slug' => $c->slug,
                'status' => $c->status,
                'position_order' => $c->position_order,
                'products_count' => (int) $c->getAttribute('products_count'),
                'thumbnail_url' => $this->url($c->thumbnail_image ?: $c->header_image),
            ])
            ->values()
            ->all();

        return Inertia::render('catalog/categories/index', ['categories' => $categories]);
    }

    public function create(): Response
    {
        return Inertia::render('catalog/categories/form', ['category' => null]);
    }

    public function store(CategoryFormRequest $request): RedirectResponse
    {
        $this->service->create($this->payload($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return to_route('admin.categories.index');
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('catalog/categories/form', [
            'category' => [
                'id' => $category->id,
                'title' => $category->title,
                'slug' => $category->slug,
                'details' => $category->details,
                'status' => $category->status,
                'position_order' => $category->position_order,
                'meta_title' => $category->meta_title,
                'meta_description' => $category->meta_description,
                'header_url' => $this->url($category->header_image),
                'header_mobile_url' => $this->url($category->header_image_mobile),
                'thumbnail_url' => $this->url($category->thumbnail_image),
            ],
        ]);
    }

    public function update(CategoryFormRequest $request, Category $category): RedirectResponse
    {
        $this->service->update($category, $this->payload($request, $category));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category updated.')]);

        return to_route('admin.categories.index');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->service->delete($category);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category deleted.')]);

        return to_route('admin.categories.index');
    }

    /**
     * Build the service payload: validated text fields + uploaded image paths.
     *
     * @return array<string, mixed>
     */
    private function payload(CategoryFormRequest $request, ?Category $category = null): array
    {
        $data = $request->safe()->only([
            'title', 'slug', 'details', 'status', 'position_order', 'meta_title', 'meta_description',
        ]);

        foreach (self::IMAGE_KEYS as $key) {
            if ($request->hasFile($key)) {
                $old = $category?->{$key};
                $data[$key] = $this->storage->store($request->file($key), 'categories');

                if (is_string($old) && $old !== '' && $old !== $data[$key]) {
                    $this->storage->delete($old);
                }
            }
        }

        return $data;
    }

    private function url(?string $path): ?string
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
