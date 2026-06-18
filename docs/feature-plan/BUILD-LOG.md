# Furnib.com — Build Log

> Running log of autonomous build progress. Newest at top. See `ROADMAP.md` / OpenSpec `tasks.md` for the task list.

## Conventions in effect
- Branch: `feat/phase-0-foundations` (Phase 0). Conventional commits. Pushed to GitHub.
- Every module: OpenSpec spec → Pest TDD (RED→GREEN) → Pint/Larastan → commit.
- PHP 8.3.16 invoked explicitly (system default is 8.1). DB: MySQL `furnib-ecommerce`.
- Integrations needing live keys (R2, SMS, SteadFast, SSLCommerz, SMTP) are built as **interfaces + services + fakes + tests**; owner plugs credentials into encrypted settings later.

## Progress

### Navigation rework (header + mobile tab bar)  ✅ (branch `feat/theming-and-category-redesign`)
- **Inquiry** button on product cards now always shows the **"Inquiry"** label + an **authentic WhatsApp glyph** (shared `WhatsAppIcon`). Verified text + icon + one-row fit on mobile.
- **Header is no longer sticky** (static) — category hero image is no longer hidden under it (desktop + mobile). Non-sticky → scrolls away on down, returns on up.
- **Desktop**: header = Logo + `Home` + theme toggle; plus **floating action buttons** (`FloatingActions`, desktop-only) — bottom-left categories menu + bottom-right green WhatsApp. No bottom bar (verified `display:none` at ≥md).
- **Mobile**: floating buttons hidden; fixed `MobileTabBar` (Categories · Home · WhatsApp), **auto-hides on scroll down, shows on scroll up** (verified via deterministic scroll dispatch). Category drawer is `CategoryDrawer` opened via a `furnib:open-categories` window event (from header/bar/floating). `main` gets `pb-16 md:pb-0` so content clears the bar.
- **Orders**: deferred — the Orders nav button and `/orders` page were removed for now (will return in Phase 3). `/orders` 404s.
- **Banners category** fully gone from storefront (drawer + home show only chair/decor-item/table); seeder skips `banners/`. Verified.
- Storefront `tsc` + `eslint` clean.

### Catalog feed redesign + Admin branding settings  ✅ (branch `feat/theming-and-category-redesign`)
**Storefront product feed (browser-verified via DOM/computed-style; screenshot tool was flaky):**
- Product card stripped to a **big full-bleed image slider** (mobile left/right padding = 0, measured 0→375) + **one-row controls** below: `[৳price (+ strikethrough discount, currency symbol only, no "PRICE" label)] [Inquiry — WhatsApp green #25D366 w/ icon] [Order Now — orange]`. Fits one row on mobile (price 148 / inquiry 42 / order 146, no wrap). Removed product name, In Stock badge, SKU and description from the card.
- Slider: thumbnails now **below** the preview in all breakpoints (single-column feed); arrows + `n/total` counter; tap thumb/arrow swaps preview.
- Feed = centered `max-w-2xl` column, tight spacing (`space-y-3`), IntersectionObserver infinite scroll (page-by-page autoload, no full load). Floating WhatsApp button recoloured **green** with WhatsApp glyph.

**Admin-managed branding (`settings.manage` gated):**
- `Setting` 'branding' group via `SettingsService`. `SiteSettingController` (edit/update) — site_name, tagline, whatsapp, contact phone/email/address + **logo (light/dark) and favicon uploads** via `StorageRepository` (old file cleaned up on replace). Routes in `routes/settings.php`. **SVG uploads disabled** (stored-XSS risk) — PNG/JPG/WebP only (favicon PNG/ICO).
- Public `GET /api/v1/settings` (`Api\SettingController`) — non-secret branding + resolved media URLs.
- Admin Inertia page `settings/site.tsx` (+ "Site & branding" nav item) with text fields, file inputs, live previews, validation errors, toast.
- **6 Pest feature tests pass** (403 gate, text save, logo upload stored, SVG rejected, bad-whatsapp rejected, public API). Pint + Larastan 0. Admin `tsc` + `eslint` clean; `vite build` succeeds.
- Storefront now **consumes** `/api/v1/settings`: dynamic `<title>`/tagline (verified "Furnib.com — Feel the Comfort" from DB), logo URLs (fallback to `/public/logo/*.png`), favicon (fallback `/logo/furnib-favicon.png`), WhatsApp number (floating + product actions). Header uses lighter **mid** logo files.
- ⚠️ **Build note:** `pnpm run build` (laravel-backend) runs `php artisan wayfinder:generate`; system `php` is 8.1 (fails). Prepend PHP 8.3 to PATH for the build.

**Home banners + footer + category images (follow-up):**
- **Category header/thumbnail** auto-set to the first product's image (`DummyCatalogSeeder::backfillCategoryImage`) — verified hero loads from R2.
- **Home banners** admin-managed: `banner_1`/`banner_2` added to branding settings (uploads, AVIF allowed), public API returns `banners[]`. Storefront `BannerCarousel` (auto-rotate, dots/arrows) shown on home when banners exist, else the gradient `Hero`. Seeded 2 defaults from `dummy-products/banners` (`DummyCatalogSeeder::seedBanners`, idempotent, excludes the SSLCommerz logo). Verified 2 banners render from R2.
- **Footer:** SSLCommerz payment logo (`/public/sslcommerz.avif`) shown just above the copyright line.
- Fix: `seedBanners` run had also created a stray **"Banners" category** (the seeder iterated `dummy-products/banners`); seeder now skips `banners/`, stray category force-deleted. Categories back to chair/table/decor-item.
- Quality: storefront `tsc`+`eslint` clean, admin `types:check`+`lint:check` clean, Pint + Larastan 0, 6 Pest tests still pass.

### Theming + Catalog UX  ✅ (branch `feat/theming-and-category-redesign`, browser-verified)
- **Light/dark theme** — `next-themes` (system default + toggle). `globals.css` rewritten with light `:root` + `.dark` token sets; brand **orange** (`--brand`/`--accent`) primary, theme-overridable. `ThemeProvider` + `ThemeToggle` (CSS-swapped icons, no hydration flash). Hero gradient now uses `var(--brand)`.
- **Logo** — `Logo` component swaps `/logo/furnib-light.svg` ↔ `/logo/furnib-dark.svg` by theme (pure CSS `.dark`). Placeholders + `public/logo/README.md` shipped; owner drops official files with same names (no code change). Slim sticky `Header` (logo left, toggle right).
- **Category page redesign** — product cards now **100% width**, mobile **0** horizontal padding (image edge-to-edge), desktop padded (`max-w-6xl px-6`). **No product detail page** (route removed); WhatsApp links point to the category page.
- **Per-product slider** (`ImageSlider`) — desktop: vertical thumbnail rail left of preview + arrows + `n/total` counter (matches reference). Mobile: thumbnails **below** preview; tap thumb or arrows to change. Verified: full-bleed on mobile (left 0 / right 375), thumb→preview swap, light+dark, desktop+mobile.
- **Demo data** — `DummyCatalogSeeder` reads `dummy-products/<category>/<product>/*.avif` (3 categories, 9 products, 33 images), uploads via `StorageRepository` to R2, sets `main_image` + gallery (≤6). Idempotent. Old placeholder products purged. `dummy-products/` gitignored.
- **AVIF note** — source images are AVIF (not "favp"); served as-is (modern-browser + Next/Image native). Admin uploads still normalise to WebP (broader PHP/GD tooling).
- Quality: storefront `tsc` + `eslint` clean.

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

### Cloudflare R2 storage ✅ ENABLED & verified (branch `feat/r2-storage`)
- Installed `league/flysystem-aws-s3-v3`; `config/filesystems.php` r2 disk wired to `R2_*` env (backend only, never client).
- Verified put/exists/delete against bucket `furnib-ecommerce`; public images served via r2.dev URL (no custom domain).
- API resources now emit **absolute image URLs** via the active `StorageRepository` (driver-agnostic: local `/storage` or R2 public URL) — new `ResolvesMediaUrls` trait.
- Storage driver setting flipped to `r2`; uploaded a demo image and **confirmed it renders on the storefront** (5 product images load from r2.dev, 900px). 108 tests, Larastan 0, Pint clean.
- ⚠️ Owner to rotate the R2 API token (secret surfaced in a session); env names: `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_ENDPOINT`, `R2_BUCKET`, `R2_DEFAULT_REGION=auto`, `R2_URL`.

### What remains (big picture)
- Phase 3: orders + web checkout + invoice PDF.
- Phase 4: SSLCommerz, OTP auth, SMS, SteadFast, SMTP (interfaces ready; need owner's API keys).
- Phase 5: GTM/GA4/Pixel/Clarity + Meta CAPI, SEO/sitemap/JSON-LD, feeds.
- Phase 6: settings surfaces, owner maintenance lock, security hardening.
