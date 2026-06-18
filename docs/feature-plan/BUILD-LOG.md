# Furnib.com — Build Log

> Running log of autonomous build progress. Newest at top. See `ROADMAP.md` / OpenSpec `tasks.md` for the task list.

## Conventions in effect
- Branch: `feat/phase-0-foundations` (Phase 0). Conventional commits. Pushed to GitHub.
- Every module: OpenSpec spec → Pest TDD (RED→GREEN) → Pint/Larastan → commit.
- PHP 8.3.16 invoked explicitly (system default is 8.1). DB: MySQL `furnib-ecommerce`.
- Integrations needing live keys (R2, SMS, SteadFast, SSLCommerz, SMTP) are built as **interfaces + services + fakes + tests**; owner plugs credentials into encrypted settings later.

## Progress

### Phase 0 — Foundations  ✅ COMPLETE (81 tests, Larastan 0 errors, Pint clean)
- ✅ 1.1 Deps installed + pinned (spatie permission/data/activitylog, sanctum, intervention/image, dompdf, league/csv).
- ✅ 1.2 Vendor configs/migrations published + migrated to `furnib-ecommerce`.
- ✅ 1.3 Baseline green — enabled RefreshDatabase in Pest.php; 39 starter tests pass.
- ✅ 2. Architecture base — RepositoryInterface + BaseRepository + UserRepository, RepositoryServiceProvider.
- ✅ 3. Money value object + MoneyCast (integer paisa).
- ✅ 4. RBAC + bootstrap — config/rbac.php matrix, PermissionRoleSeeder, OwnerSeeder (a@a.com) + AdminSeeder (admin@gmail.com), `must_change_password`/`two_factor_required` + `EnsureAccountSecured` middleware, no-backdoor guard test. **62 tests pass.**
  - Note: argon2id needs Linux/libsodium; on Windows local we hash with bcrypt. Set `HASH_DRIVER=argon2id` in prod.
  - Note: bootstrap creds live in `.env` only (a@a.com / admin@gmail.com), forced change + (owner) 2FA on first login.
- ✅ 5. Audit logging — Auditable trait (LogsActivity), Activity IP hook, gated read endpoint (`audit.view`).
- ✅ 6. Encrypted settings — SettingsService (typed casts, Crypt secrets, masking).
- ✅ 7. Storage abstraction — StorageRepository (server default, R2 switchable), loud-fail on missing R2 creds.
- ✅ 8. API foundation — `/api/v1`, Sanctum, uniform error envelope (`ApiExceptionRenderer`), rate limiters (otp/auth/orders).

**Phase 0 done & merged to master.**

### Phase 1 — Catalog  (in progress on `feat/phase-1-catalog`)
- ✅ OpenSpec change proposed (5 capability specs + tasks), validated.
- ✅ 1. Category module — migration, model (SEO/softDeletes/scopes), factory, repository, service (auto unique slug), admin CRUD (RBAC-gated) + storefront list/show API + resource, audited. **90 tests, Larastan 0, Pint clean.**
  - Fixed a latent bug: `shouldRenderJsonWhen` now also honours `expectsJson()`, so admin JSON endpoints return 422 envelopes instead of 302 redirects on validation errors.
- ✅ 2. Product module (data layer) — products + product_images migrations, Product/ProductImage models (Money cast paisa, soft deletes, relations, stock logic, social-thumbnail fallback), factory, ProductRepository + ProductService (auto slug/sku, max-6 images rule). Admin write CRUD endpoints PENDING.
- ✅ 4. Catalog read API — `/api/v1/categories` (active list), `/api/v1/categories/{slug}` (with paginated published products), `/api/v1/products/{slug}` (404 on draft). Category/Product resources. **99 tests, Larastan 0, Pint clean.**
- ✅ 2.4 Admin product CRUD — store/update/destroy gated by `catalog.manage` (StoreProductRequest/UpdateProductRequest).
- ✅ 3. Image optimization — `ImageOptimizer` (intervention/image v4: decodePath + WebpEncoder) → WebP via `StorageRepository::put()`; large images downscaled.
- ✅ 5. Admin listing & lifecycle — `adminPaginate` (search title/SKU/slug, filter status/category/stock/date, sort, paginate), recycle bin (trashed/restore/forceDelete), `ExportProductsCsv` (league/csv).
- ✅ 6.1 Quality gate: **108 tests, Larastan 0, Pint clean.** Sample data seeder (`CatalogSampleSeeder`) → 2 categories, 10 products locally.

**Phase 1 backend COMPLETE.** Pending for later: admin **Inertia React UI** pages (pair with Phase 2 storefront). Phase 1 ready to merge + tag v0.1.0.

### Phase 2 — Storefront ✅ COMPLETE (branch `feat/phase-2-storefront`, browser-verified)
Next.js 16 storefront consuming the catalog API, dark mobile-first theme:
- API client + types (`lib/`), `revalidate: 60`; infinite scroll proxied via a same-origin Next route handler (no CORS needed).
- Home: hero + CTAs, Featured Collections (live categories, 2-col/1-col), info section, footer.
- Floating WhatsApp (bottom-right) + menu drawer (bottom-left, lists categories).
- Category page: 75vh header image, title/details, products 1-per-row, IntersectionObserver infinite scroll, "Explore Collections" grid at end.
- Product: image slider (arrows+thumbnails, safe-image fallback), Price (struck-through discount) / Inquiry (WhatsApp deep link) / Order Now → modal (qty stepper, Order on WhatsApp + Order on Web→checkout stub). Product detail route with SEO metadata + YouTube embed.
- **Verified live in browser** (home, category w/ 5 products, order modal, product page, mobile drawer). tsc + eslint clean.
- Pending: admin Inertia React UI; web checkout is a stub until Phase 3.

### What remains (big picture)
- Phase 3: orders + web checkout + invoice PDF.
- Phase 4: SSLCommerz, OTP auth, SMS, SteadFast, SMTP (interfaces ready; need owner's API keys).
- Phase 5: GTM/GA4/Pixel/Clarity + Meta CAPI, SEO/sitemap/JSON-LD, feeds.
- Phase 6: settings surfaces, owner maintenance lock, security hardening.
