## Why

The storefront is a catalog of categories and products with no cart — browsing and WhatsApp/web inquiry. We need the catalog data layer, admin management, optimized images, and the read API the Next.js storefront consumes. This is the core domain of Furnib.com.

## What Changes

- Add `categories` and `products` tables (+ `product_images`) with SEO fields, soft deletes, and money stored as integer paisa.
- Category management: CRUD with header/thumbnail images, details, status, ordering, SEO.
- Product management: CRUD with one main image + up to 6 gallery images, YouTube video, price/discount, advance-payment rules, stock + stock_status logic, flags (featured/new), ordering, SEO, social thumbnail fallback.
- Image pipeline: uploads optimized to responsive WebP via the `media-storage` abstraction.
- Admin product listing: search/filter/sort/paginate; soft delete + recycle bin (restore / hard delete); CSV export.
- Storefront read API under `/api/v1`: categories list, single category with paginated products, single product.

## Capabilities

### New Capabilities
- `category-management`: CRUD, ordering, status, images, and SEO for categories.
- `product-management`: CRUD for products incl. images, pricing, advance-payment rules, stock logic, flags, SEO.
- `image-optimization`: converting uploaded images to optimized responsive WebP through the storage abstraction.
- `catalog-api`: storefront read endpoints for categories and products (lists + detail, paginated).
- `product-admin-listing`: server-side search/filter/sort/paginate, recycle bin (soft delete/restore/hard delete), CSV export.

### Modified Capabilities
<!-- none -->

## Impact

- **Code**: `app/Models`, `app/Repositories`, `app/Services/Catalog`, `app/Actions/Catalog`, `app/Http/Controllers/{Admin,Api}`, `app/Http/Resources`, `database/{migrations,factories,seeders}`, `routes/api.php`.
- **Depends on** Phase 0: Money cast, StorageRepository, RBAC permissions (`catalog.view`/`catalog.manage`), audit logging, api-foundation.
- **Frontend**: storefront consumes `catalog-api` from Phase 2; admin Inertia UI built alongside.
