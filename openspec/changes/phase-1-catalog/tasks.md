## 1. Category module (category-management)

- [x] 1.1 RED: management + API tests (auto-slug, duplicate rejected, authz 403, audit, active-only list, 404)
- [x] 1.2 GREEN: `categories` migration, `Category` model (SEO, softDeletes, scopes), factory
- [x] 1.3 GREEN: `CategoryRepository` + `CategoryService` (auto-slug, ordering, active scope), audited
- [x] 1.4 GREEN: admin CRUD (StoreategoryRequest/UpdateCategoryRequest, gated `catalog.manage`) + storefront list/show API + CategoryResource. Fixed latent JSON-exception bug (shouldRenderJsonWhen now honours expectsJson).

## 2. Product module (product-management)

- [x] 2.1 RED: tests — money paisa, unique SKU, stock logic, social-thumbnail fallback, max-6 images
- [x] 2.2 GREEN: `products` + `product_images` migrations, `Product`/`ProductImage` models (Money cast, softDeletes, relations)
- [x] 2.3 GREEN: `ProductRepository` + `ProductService` (auto slug/sku, stock accessor, max-6 images rule)
- [ ] 2.4 admin CRUD endpoints gated by `catalog.manage` — PENDING (storefront API done; admin write endpoints next)

## 3. Image optimization (image-optimization)

- [ ] 3.1 RED: tests — upload converted to WebP + stored via StorageRepository; large image downscaled
- [ ] 3.2 GREEN: `ImageOptimizer` service (intervention/image) writing through StorageRepository

## 4. Catalog read API (catalog-api)

- [x] 4.1 RED: tests — categories list (active only), category+products by slug (paginated), product by slug; drafts/inactive 404
- [x] 4.2 GREEN: `Api\CategoryController` (list + show-with-products) / `Api\ProductController` (show) + Category/Product Resources under `/api/v1`

## 5. Admin listing & lifecycle (product-admin-listing)

- [ ] 5.1 RED: tests — search by SKU, filter by status, soft delete/restore, hard delete, CSV export
- [ ] 5.2 GREEN: listing query (search/filter/sort/paginate), recycle-bin endpoints, CSV export action

## 6. Verify & ship

- [ ] 6.1 Pest green, Larastan clean, Pint clean
- [ ] 6.2 Seed sample categories/products for local; merge to master; tag v0.1.0
