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
- ⏳ 3. Image optimization (WebP via intervention/image + StorageRepository) — next.
- ⏳ 2.4 Admin product write CRUD — next.
- ⏳ 5. Admin product listing — search/filter/sort, recycle bin, CSV export — next.
