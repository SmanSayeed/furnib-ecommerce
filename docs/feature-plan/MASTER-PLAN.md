# Lovinna E-Commerce — Master Plan

> Status: DRAFT for approval · Owner: Saadman Sayeed · Date: 2026-06-18
> Stack detected in repo: **Laravel 13 / PHP 8.3 / Inertia v3 + React 19 (admin) / Fortify / Wayfinder / Pest 4 / Larastan / Pint** · **Next.js 16 / React 19 / Tailwind 4 / shadcn (storefront)** · **MySQL**

---

## 0. Security decisions (READ FIRST — non-negotiable)

1. **No destructive "root" backdoor.** The requested account that deletes/locks server folders is **rejected**. See `SECURITY.md` §1. Replaced by:
   - An **`owner`** role (highest legitimate role) — hashed (argon2id), seeded from env, **forced password rotation + mandatory 2FA on first login**. Never committed.
   - A reversible **Maintenance Lock** (Laravel maintenance mode + session revocation + key rotation), fully **audit-logged**. No filesystem deletion anywhere in the codebase.
2. **No hardcoded credentials.** `admin@gmail.com / 11112222` and the owner creds become **env-seeded bootstrap values** that must be changed on first login. `.env` is git-ignored; secrets go to env / secret store only.
3. **All third-party secrets** (SSLCommerz, SteadFast, SMS gateway, R2, SMTP, CAPI tokens) are stored **encrypted at rest** in DB (`Crypt::`/Laravel encrypted casts) or env — **never** in the Next.js bundle and **never** in `NEXT_PUBLIC_*`. Only genuinely-public IDs (GTM container, GA4 measurement ID, Meta Pixel ID) may reach the client.
4. Every module ships with: input validation (FormRequest/Zod), authorization (Policy + RBAC gate), ownership checks on `:id` routes (no IDOR), rate limiting on public/auth/order/OTP endpoints, and tests **before** merge.

---

## 1. Architecture

```
md-yeasir-hasan-lovinna-ecommerce/
├─ laravel-backend/        # Admin panel (Inertia+React+shadcn) + REST/JSON API for storefront
├─ ecommerce-next-frontend/# Public storefront (Next 16, SSR/ISR, shadcn)
└─ docs/feature-plan/      # specs, plan, openspec changes
```

- **Admin panel** = Laravel + Inertia + React 19 + shadcn (already scaffolded via Fortify starter). Server-rendered, session-auth, CSRF-protected.
- **Storefront** = Next.js 16. Talks to Laravel over a **versioned JSON API** (`/api/v1/...`). Public reads cached via **ISR**; order/auth via **server actions / route handlers** (secrets stay server-side).
- **Auth boundaries**
  - Admin/staff → Fortify session + 2FA (Inertia).
  - Storefront customers → **mobile + OTP** login issuing **Sanctum** tokens (short-lived) used by Next server-side only.
- **DB**: MySQL 8 (utf8mb4). Money stored as integer **minor units** (paisa) — never floats. Soft deletes + a real **recycle bin** for products.
- **Storage**: `StorageRepository` interface with `ServerDisk` and `CloudflareR2` drivers, switchable from admin settings (Strategy pattern). Image pipeline → optimized **WebP** + responsive sizes.

### Laravel layering (SOLID / DRY)
```
Http/Controllers  → thin; validate (FormRequest) → call Service → return Resource/Inertia
Services/*        → business logic, transactions, orchestration
Repositories/*    → data access (interface + Eloquent impl), bound in a ServiceProvider
DTOs/*            → typed data transfer (spatie/laravel-data) between layers
Actions/*         → single-purpose use-cases (PlaceOrder, ExportProductsCsv, …)
Policies/*        → authorization
Support/*         → integrations (SslCommerz, SteadFast, Sms, Capi) behind interfaces
```

---

## 2. Workflow — OpenSpec + TDD (how we actually build)

`openspec` is installed globally. Each module = one **openspec change** before code.

**Per-module loop:**
1. `openspec` change: write spec (requirements, scenarios, data shape, API contract) under `docs/feature-plan/openspec/changes/<module>/`.
2. **Red** — write Pest tests (feature + unit) from the spec scenarios; they fail.
3. **Green** — implement Service/Repo/Controller until tests pass.
4. **Refactor** — Pint format, Larastan level max, remove duplication.
5. Frontend: Vitest/RTL (admin) + Playwright (storefront e2e for critical flows: order, payment return, OTP).
6. Mark openspec change as deployed → archive.

**Definition of Done (every module):** tests green · Larastan clean · Pint clean · authorization + validation present · no secret in client bundle · audit log on sensitive writes · openspec archived.

Context7 MCP is used during implementation to pull **current** Laravel 13 / Next 16 / Fortify / Sanctum / Inertia v3 / Tailwind 4 APIs (training data is stale; Next 16 has breaking changes — read `node_modules/next/dist/docs/` too).

---

## 3. Data model (MySQL, money in minor units)

**Catalog**
- `categories` — id, title, slug(unique), details(longtext, SEO), header_image, thumbnail_image(OG), status, position_order, timestamps, softDeletes + SEO fields (meta_title, meta_description, og_image).
- `products` — id, category_id(FK), title, slug(unique), sku(unique), details(nullable), product_video(nullable yt url), price(int), discount_price(int nullable), main_image, is_advance_payment(bool), advance_payment_type(enum full|partial), partial_amount_type(enum percentage|amount nullable), partial_amount(int nullable), is_featured, is_new, position_order, product_status(enum draft|published|disabled), stock_amount(int), stock_status(bool), social_thumbnail_image(nullable→falls back to main_image), SEO fields, timestamps, softDeletes.
- `product_images` — id, product_id(FK), path, webp_variants(json), alt_text(SEO), position (max 6 enforced in Service).

**Orders & customers**
- `customers` — id, name, mobile(unique, +88 normalized), email(nullable), created via order/OTP. Sanctum tokens.
- `otp_codes` — mobile, code(hashed), expires_at, attempts (rate-limited).
- `orders` — id, order_no(unique), customer_id, status(enum pending|confirmed|processing|shipped|delivered|cancelled|returned), subtotal, shipping_cost, total, advance_paid, payment_status(enum unpaid|partial|paid), shipping_zone_id, address(text), customer_ip, user_agent, notes, timestamps, softDeletes.
- `order_items` — order_id, product_id, title/sku/price snapshot, qty, line_total.
- `shipping_zones` — name, cost(int), status.
- `payments` — order_id, gateway(sslcommerz), amount, type(full|partial|shipping), tran_id, val_id, status, raw_payload(encrypted), timestamps.
- `shipments` — order_id, courier(steadfast), consignment_id, tracking_code, status, raw_payload.

**RBAC** (spatie/laravel-permission)
- `roles`, `permissions`, pivots. Roles seeded: `owner`, `admin`, `manager`, `sub-admin`, `marketer`, `editor`. (Permission matrix in §6.)

**Settings / Marketing / SEO** (key-value `settings` table, grouped + cast; secrets encrypted)
- groups: `contact`, `home`, `footer`, `seo`, `marketing`, `storage`, `payment`, `sms`, `smtp`, `courier`.
- `seo_meta` (polymorphic) for per-entity overrides; `home_page_settings`; `banners`.

**Audit & ops**
- `activity_log` (spatie/laravel-activitylog) — all sensitive writes + owner actions + IP.
- `visitors` — session, IP, UA, path, referrer, utm, timestamps (for visitor tracking).
- `csv_exports` / product feed cache (Meta commerce catalog).

---

## 4. Build phases (sequenced, each = openspec change + TDD)

**Phase 0 — Foundations**
- Add deps: `spatie/laravel-permission`, `spatie/laravel-data`, `spatie/laravel-activitylog`, `spatie/laravel-medialibrary` (or custom image service), `laravel/sanctum`, `intervention/image` (WebP), `barryvdh/laravel-dompdf` (invoices), `league/csv`.
- Repository/Service/DTO base + provider bindings. Money cast. Audit + RBAC seeders (roles + owner bootstrap from env, forced 2FA). Storage abstraction (Server + R2). Encrypted settings service.
- Standard error handling, API resource layer, rate limiters.

**Phase 1 — Catalog (admin + API)**
- Category CRUD (image/banner/SEO/status/order). Product CRUD: single main + up to 6 images, WebP optimization pipeline, video, price/discount, advance-payment rules, stock + stock_status logic (`in stock` only if `stock_status && stock_amount>0`), is_featured/is_new/position_order.
- Product list: server-side table — search (keyword/SKU/slug), filters (status/category/stock/date range), sort, pagination. Edit/view/soft-delete, **recycle bin** (restore / hard-delete), **CSV export**.

**Phase 2 — Storefront read paths (Next)**
- Home: full-width banner, hero CTA (WhatsApp/Browse/Profile), Featured Collections (2-col desktop / 1-col mobile category cards), "price shown in photos" section, footer (contact/email/whatsapp/address).
- Floating **WhatsApp button** (bottom-right) + floating **menu button** (bottom-left) → left drawer category list.
- Category page: 80vh full-width header image, name+detail, **infinite-scroll** product list (1 product per row), categories grid at the very end. ISR + suspense + skeletons.
- Product (in category, full-width): image slider (arrows + thumbnails), 3 buttons → **Price** (struck-through original + discount), **Inquiry** (WhatsApp deep link w/ image preview, title, SKU, details), **Order Now** (modal: image, sku, qty +/-, "Order on WhatsApp" / "Order on Web").

**Phase 3 — Orders & checkout**
- Web checkout page: qty +/-, name, mobile (readonly `+88`, 11-digit BD validation), address, shipping zone select, live subtotal+shipping=total, optional email. Advance-payment notices per product rule (full / partial / shipping-only).
- Place order → customer auto-registered by mobile (OTP), order persisted with **customer IP**, SMS sent, redirect to **success/celebration page** with order details + **invoice PDF** download + optional pay buttons (full / shipping) via SSLCommerz.
- Admin orders: list (filter/sort/search by status/mobile/name/address/date), view page, status change, **invoice PDF**.

**Phase 4 — Payments / SMS / Courier / Customer auth**
- **SSLCommerz** (dynamic creds): init, IPN/validation, success/fail/cancel pages, receipt return. Server-side verification of `val_id` (never trust redirect). Idempotent payment recording.
- **OTP auth** (customer): request/verify, hashed OTP, rate limit, Sanctum tokens; registration/login UI.
- **SMS gateway** (dynamic) — order confirmation. **SteadFast** courier (dynamic) — create consignment, track.
- **SMTP** dynamic settings + test-send.

**Phase 5 — Marketing / SEO / Analytics**
- Dynamic **GTM / GA4 / Meta Pixel / Microsoft Clarity** injection into Next from settings (public IDs only).
- **Server-side tracking**: Meta **CAPI** / Conversion API (token server-side), event dedup, click/ViewContent/AddToCart-equivalent/Purchase events.
- **Visitor tracking** + IP capture. Dynamic **SEO** (meta/OG/canonical/structured data Product+Breadcrumb JSON-LD), sitemap.xml, robots.txt, dynamic OG thumbnails (home falls back to header banner; product/category fall back to their images).
- **Meta commerce / Google Merchant**: scheduled **CSV/feed export** + product feed endpoint.

**Phase 6 — Settings surfaces & hardening**
- Admin editors: contact, home banner, footer, WhatsApp number, all gateway settings.
- Owner Maintenance Lock + session revocation + key rotation (reversible, audited).
- Full security pass: rate limits, headers (CSP/HSTS), CORS lockdown to storefront origin, `npm/composer audit`, Larastan max, load/perf check, backup strategy.

---

## 5. Storefront (Next 16) rendering strategy
- Home / category / product pages: **ISR** (revalidate on admin publish via on-demand revalidation webhook).
- Checkout, order success, auth: **dynamic / server actions** (no caching, secrets server-side).
- `loadash`(lodash) for debounced search; optimistic UI for qty/cart-modal; Suspense + skeleton loaders; Zod validation on all forms; mobile-first responsive.
- API secrets accessed only in server components / route handlers; client gets data via fetch to our own server.

## 6. RBAC matrix (seeded; not editable from UI yet, architecture supports it)
| Permission group | owner | admin | manager | sub-admin | marketer | editor |
|---|---|---|---|---|---|---|
| catalog (CRUD) | ✓ | ✓ | ✓ | ✓ | – | ✓(edit) |
| orders view/manage | ✓ | ✓ | ✓ | ✓(view) | – | – |
| revenue/payments | ✓ | ✓ | – | – | – | – |
| marketing/SEO/analytics | ✓ | ✓ | – | – | ✓ | – |
| settings/gateways | ✓ | ✓ | – | – | – | – |
| roles/users | ✓ | ✓ | – | – | – | – |
| maintenance lock / key rotation | ✓ | – | – | – | – | – |
| audit log | ✓ | ✓ | – | – | – | – |

## 7. Testing strategy
- **Pest** feature tests per endpoint (auth, validation, policy, happy + edge). Unit tests for Services/DTOs/money math/advance-payment rules.
- Payment + courier + SMS integrations behind interfaces → **faked** in tests; one contract test against sandbox.
- Storefront: **Playwright** e2e for order flow, SSLCommerz return, OTP login. Vitest/RTL for admin components.
- CI gates: Pint, Larastan, Pest, eslint, type-check.

## 8. Decisions (locked 2026-06-18)
- **Owner access**: Secure `owner` role only (env-seeded, forced password change + mandatory 2FA, audit-logged, reversible Maintenance Lock). **No** destructive backdoor. **No** remote takedown switch for now.
- **Default storage**: **Server disk (local)** — R2 driver still implemented and switchable from admin settings later.
- **SMS gateway**: build a **provider-agnostic `SmsGateway` interface** now; concrete BD adapter plugged in later.

### Still open
- Invoice/receipt template branding assets (logo, address — Lovinna Enterprise details available in footer spec).
- PDPA/privacy note for IP + visitor tracking (disclose in privacy policy).
```
```

---
*Companion docs to be generated on approval:* `SECURITY.md` (threat model + the rejected-backdoor rationale + secure owner design), `DATA-MODEL.md` (full migrations), and per-module `openspec` changes starting with Phase 0.
