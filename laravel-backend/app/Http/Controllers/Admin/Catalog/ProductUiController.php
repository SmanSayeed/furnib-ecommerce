<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductBulkActionRequest;
use App\Http\Requests\Admin\ProductFormRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Eloquent\ProductRepository;
use App\Services\Catalog\ImageOptimizer;
use App\Services\Catalog\ProductService;
use App\Storage\Contracts\StorageRepository;
use App\Support\Lists\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductUiController extends Controller
{
    private const MAX_GALLERY = 6;

    public function __construct(
        private readonly ProductService $service,
        private readonly ProductRepositoryInterface $products,
        private readonly ImageOptimizer $optimizer,
        private readonly StorageRepository $storage,
    ) {}

    public function index(Request $request): Response
    {
        $listQuery = ListQuery::fromRequest($request, ProductRepository::listConfig());
        $paginator = $this->products->adminList($listQuery);

        return Inertia::render('catalog/products/index', [
            'products' => collect($paginator->items())
                ->map(fn (Product $p): array => $this->listRow($p))
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'search' => $listQuery->search ?? '',
                'status' => $listQuery->filters['product_status'] ?? '',
                'category_id' => $listQuery->filters['category_id'] ?? '',
                'sort' => $listQuery->sort,
                'dir' => $listQuery->dir,
                'range' => $listQuery->dateRange->preset,
                'from' => (string) $request->query('from', ''),
                'to' => (string) $request->query('to', ''),
            ],
            'categories' => $this->categoryOptions(),
            'trashedCount' => Product::onlyTrashed()->count(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('catalog/products/form', [
            'product' => null,
            'categories' => $this->categoryOptions(),
            'zones' => $this->zoneOptions(),
        ]);
    }

    public function store(ProductFormRequest $request): RedirectResponse
    {
        $data = $this->scalarPayload($request);
        $data['main_image'] = $this->storeImage($request, 'main_image');
        $data['social_thumbnail_image'] = $this->storeImage($request, 'social_thumbnail_image');

        $product = $this->service->create($data);
        $this->syncGallery($product, $request);
        $this->syncShippingCharges($product, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product created.')]);

        return to_route('admin.products.index');
    }

    public function edit(Product $product): Response
    {
        $product->load('images', 'shippingCharges');

        return Inertia::render('catalog/products/form', [
            'product' => $this->formData($product),
            'categories' => $this->categoryOptions(),
            'zones' => $this->zoneOptions(),
        ]);
    }

    public function update(ProductFormRequest $request, Product $product): RedirectResponse
    {
        $data = $this->scalarPayload($request);

        foreach (['main_image', 'social_thumbnail_image'] as $key) {
            if ($request->hasFile($key)) {
                $old = $product->{$key};
                $data[$key] = $this->storeImage($request, $key);

                if (is_string($old) && $old !== '' && $old !== $data[$key]) {
                    $this->storage->delete($old);
                }
            }
        }

        $product->update($data);
        $this->syncGallery($product, $request);
        $this->syncShippingCharges($product, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product updated.')]);

        return to_route('admin.products.index');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product moved to recycle bin.')]);

        return to_route('admin.products.index');
    }

    /**
     * Apply one edit (advance payment, status, or category) to many products at
     * once. Targets are either the explicitly-ticked ids or every product
     * matching the current filters ("select all matching"), resolved server-side
     * through the same whitelist as the list. The update runs as a single query
     * per chunk (scales to a large catalog) and is captured as one audit entry.
     */
    public function bulk(ProductBulkActionRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $ids = $request->boolean('all_matching')
            ? $this->products->idsMatching(is_array($data['filters'] ?? null) ? $data['filters'] : [])
            : array_values(array_unique(array_map('intval', $data['ids'] ?? [])));

        if ($ids === []) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => __('No products selected.')]);

            return to_route('admin.products.index');
        }

        $changes = $this->bulkChanges($data);
        $affected = 0;

        // Chunk the id set so the IN clause and row-lock footprint stay bounded
        // even for a whole-catalog selection.
        foreach (array_chunk($ids, 500) as $chunk) {
            $affected += Product::query()->whereIn('id', $chunk)->update($changes);
        }

        // Bulk writes bypass per-model events, so record one explicit audit entry
        // (who, which action, how many, and the values applied).
        activity()
            ->useLog('Product')
            ->causedBy($request->user())
            ->withProperties([
                'action' => $data['action'],
                'count' => $affected,
                'changes' => $changes,
            ])
            ->log('bulk_update');

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':count products updated.', ['count' => $affected]),
        ]);

        return to_route('admin.products.index');
    }

    /**
     * Translate a validated bulk request into the exact column changes to write.
     * Mirrors the single-form rules: partial fields only survive a "partial"
     * advance; turning advance off clears the partial config.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function bulkChanges(array $data): array
    {
        return match ($data['action']) {
            'status' => ['product_status' => $data['product_status']],
            'category' => ['category_id' => (int) $data['category_id']],
            'advance' => $this->advanceChanges($data),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function advanceChanges(array $data): array
    {
        $isAdvance = (bool) ($data['is_advance_payment'] ?? false);

        if (! $isAdvance) {
            return [
                'is_advance_payment' => false,
                'partial_amount_type' => null,
                'partial_amount' => null,
            ];
        }

        $type = $data['advance_payment_type'] ?? 'full';

        return [
            'is_advance_payment' => true,
            'advance_payment_type' => $type,
            'partial_amount_type' => $type === 'partial' ? ($data['partial_amount_type'] ?? null) : null,
            'partial_amount' => $type === 'partial' ? ($data['partial_amount'] ?? null) : null,
        ];
    }

    public function trashed(): Response
    {
        $items = Product::onlyTrashed()
            ->with('category')
            ->latest('deleted_at')
            ->get()
            ->map(fn (Product $p): array => [
                'id' => $p->id,
                'title' => $p->title,
                'sku' => $p->sku,
                'category' => $p->category?->title,
                'deleted_at' => $p->deleted_at?->toDateTimeString(),
                'main_image_url' => $this->url($p->main_image),
            ])
            ->all();

        return Inertia::render('catalog/products/trashed', ['products' => $items]);
    }

    public function restore(int $id): RedirectResponse
    {
        $product = $this->products->findWithTrashed($id);
        abort_if($product === null, 404);

        $product->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product restored.')]);

        return to_route('admin.products.trashed');
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $product = $this->products->findWithTrashed($id);
        abort_if($product === null, 404);

        foreach ($product->images()->get() as $image) {
            $this->storage->delete($image->path);
            $image->delete();
        }

        if (is_string($product->main_image) && $product->main_image !== '') {
            $this->storage->delete($product->main_image);
        }

        $product->forceDelete();

        Inertia::flash('toast', ['type' => 'warning', 'message' => __('Product permanently deleted.')]);

        return to_route('admin.products.trashed');
    }

    /**
     * Validated scalar fields (no files), ready for create/update.
     *
     * @return array<string, mixed>
     */
    private function scalarPayload(ProductFormRequest $request): array
    {
        return $request->safe()->only([
            'category_id', 'title', 'slug', 'sku', 'details', 'product_video',
            'price', 'discount_price', 'is_advance_payment', 'advance_payment_type',
            'partial_amount_type', 'partial_amount', 'is_featured', 'is_new',
            'position_order', 'product_status', 'stock_amount', 'stock_status',
            'shipping_charge_allowed', 'meta_title', 'meta_description',
        ]);
    }

    private function storeImage(ProductFormRequest $request, string $key): ?string
    {
        $file = $request->file($key);

        return $file === null ? null : $this->optimizer->optimizeAndStore($file, 'products');
    }

    /**
     * Rebuild the gallery from the `gallery_layout` JSON: reorder kept images,
     * append newly uploaded ones, and delete any existing image left out.
     * Capped at six images total. A null layout leaves the gallery untouched.
     */
    private function syncGallery(Product $product, ProductFormRequest $request): void
    {
        $raw = $request->input('gallery_layout');

        if (! is_string($raw) || $raw === '') {
            return;
        }

        /** @var mixed $layout */
        $layout = json_decode($raw, true);

        if (! is_array($layout)) {
            return;
        }

        $layout = array_slice($layout, 0, self::MAX_GALLERY);
        $newFiles = $request->file('gallery_new', []);
        $existing = $product->images()->get()->keyBy('id');
        $keptIds = [];
        $position = 0;

        foreach ($layout as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $type = $entry['type'] ?? null;

            if ($type === 'existing') {
                $image = $existing->get((int) ($entry['id'] ?? 0));

                if ($image !== null) {
                    $image->update(['position' => $position]);
                    $keptIds[] = $image->id;
                    $position++;
                }
            } elseif ($type === 'new') {
                $file = $newFiles[(int) ($entry['index'] ?? -1)] ?? null;

                if ($file !== null) {
                    $product->images()->create([
                        'path' => $this->optimizer->optimizeAndStore($file, 'products'),
                        'position' => $position,
                    ]);
                    $position++;
                }
            }
        }

        foreach ($existing as $image) {
            if (! in_array($image->id, $keptIds, true)) {
                $this->storage->delete($image->path);
                $image->delete();
            }
        }
    }

    /**
     * Replace the product's per-zone shipping charges from the submitted
     * `shipping_charges` array. Only entries with a positive extra are kept;
     * blanks/zeros remove the row. Absent field leaves charges untouched.
     */
    private function syncShippingCharges(Product $product, ProductFormRequest $request): void
    {
        // A free-shipping product carries no per-zone charges: wipe any existing
        // rows and skip — the form disables this section, but enforce it here too
        // so the two can never drift.
        if (! $product->shipping_charge_allowed) {
            $product->shippingCharges()->delete();

            return;
        }

        /** @var mixed $rows */
        $rows = $request->validated('shipping_charges');

        if (! is_array($rows)) {
            return;
        }

        $product->shippingCharges()->delete();

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $zoneId = (int) ($row['shipping_zone_id'] ?? 0);
            $extra = $row['extra_cost'] ?? null;

            if ($zoneId <= 0 || $extra === null || $extra === '' || (float) $extra <= 0) {
                continue;
            }

            $product->shippingCharges()->create([
                'shipping_zone_id' => $zoneId,
                'extra_cost' => $extra, // display amount; MoneyCast → minor units
            ]);
        }
    }

    /**
     * @return array<int, array{id:int, name:string, base:string}>
     */
    private function zoneOptions(): array
    {
        return ShippingZone::query()
            ->active()
            ->ordered()
            ->get()
            ->map(fn (ShippingZone $z): array => [
                'id' => $z->id,
                'name' => $z->name,
                'base' => $z->cost->format(),
            ])
            ->all();
    }

    /**
     * @return array<int, array{id:int, title:string}>
     */
    private function categoryOptions(): array
    {
        return Category::query()
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn (Category $c): array => ['id' => $c->id, 'title' => $c->title])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function listRow(Product $product): array
    {
        return [
            'id' => $product->id,
            'title' => $product->title,
            'sku' => $product->sku,
            'category' => $product->category?->title,
            'price' => $product->price->format(),
            'discount_price' => $product->discount_price?->format(),
            'stock_amount' => $product->stock_amount,
            'in_stock' => $product->isInStock(),
            'product_status' => $product->product_status,
            'main_image_url' => $this->url($product->main_image),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Product $product): array
    {
        return [
            'id' => $product->id,
            'category_id' => $product->category_id,
            'title' => $product->title,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'details' => $product->details,
            'product_video' => $product->product_video,
            'price' => $product->price->toDisplay(),
            'discount_price' => $product->discount_price?->toDisplay(),
            'is_advance_payment' => $product->is_advance_payment,
            'advance_payment_type' => $product->advance_payment_type,
            'partial_amount_type' => $product->partial_amount_type,
            'partial_amount' => $product->partial_amount,
            'is_featured' => $product->is_featured,
            'is_new' => $product->is_new,
            'position_order' => $product->position_order,
            'product_status' => $product->product_status,
            'stock_amount' => $product->stock_amount,
            'stock_status' => $product->stock_status,
            'shipping_charge_allowed' => $product->shipping_charge_allowed,
            'meta_title' => $product->meta_title,
            'meta_description' => $product->meta_description,
            'main_image_url' => $this->url($product->main_image),
            'social_thumbnail_url' => $this->url($product->social_thumbnail_image),
            'gallery' => $product->images->map(fn ($img): array => [
                'id' => $img->id,
                'url' => $this->url($img->path),
            ])->all(),
            'shipping_charges' => $product->shippingCharges->map(fn ($c): array => [
                'shipping_zone_id' => $c->shipping_zone_id,
                'extra_cost' => $c->extra_cost->toDisplay(),
            ])->all(),
        ];
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
