# Furnib.com — Step-by-Step Build Roadmap

> Companion to `MASTER-PLAN.md`. This is the **ordered task list** we execute. Each module follows the same loop below. Tick boxes as we go.

## The repeatable loop (every single module)
1. **`/opsx:propose`** — write the OpenSpec change (requirements, scenarios, API contract, data shape) under `openspec/changes/<id>/`.
2. **RED** — write Pest tests (feature + unit) from the scenarios; they fail.
3. **GREEN** — implement Service → Repository → DTO → Controller until tests pass.
4. **REFACTOR** — Pint format, Larastan max, kill duplication.
5. **Frontend** (if any) — Vitest/RTL (admin) or Playwright (storefront e2e).
6. **Gate (DoD):** Pest green · Larastan clean · Pint clean · eslint/type clean · authz + validation present · no secret in client bundle · audit log on sensitive writes.
7. **`/opsx:archive`** — archive the change → commit on `feat/<module>` → merge `master` → push.

Money = integer paisa everywhere. context7 for current Laravel 13 / Next 16 / Fortify / Sanctum / Inertia v3 / Tailwind 4 APIs before coding.

---

## PHASE 0 — Foundations  `feat/phase-0-foundations`
**Features:** clean architecture base, RBAC, audit, storage abstraction, secure owner.
- [ ] 0.1 Install deps: `spatie/laravel-permission`, `spatie/laravel-data`, `spatie/laravel-activitylog`, `laravel/sanctum`, `intervention/image`, `barryvdh/laravel-dompdf`, `league/csv`.
- [ ] 0.2 Base layer: `Repositories/` (interface + Eloquent impl + binding provider), `Services/`, `DTOs/`, `Actions/`, `Support/` integration interfaces.
- [ ] 0.3 Money cast (integer paisa) + `Money` value object + tests.
- [ ] 0.4 RBAC: roles+permissions migration/seeder — `owner, admin, manager, sub-admin, marketer, editor` (matrix from MASTER-PLAN §6).
- [ ] 0.5 Owner bootstrap from env (`OWNER_EMAIL`, one-time `OWNER_BOOTSTRAP_PASSWORD`), forced password change + **mandatory 2FA** (Fortify) on first login.
- [ ] 0.6 Audit log (activitylog) wired to sensitive writes.
- [ ] 0.7 `StorageRepository` interface + `ServerDisk` (default) + `CloudflareR2` drivers; switchable via settings.
- [ ] 0.8 Encrypted `settings` service (grouped, secrets via encrypted casts).
- [ ] 0.9 API skeleton: `/api/v1`, Sanctum, JSON resources, standard error handler, rate limiters.

## PHASE 1 — Catalog (admin + API)  `feat/phase-1-catalog`
**Features:** Categories, Products, image pipeline, product list/manage, recycle bin, CSV.
- [ ] 1.1 Category CRUD — title, slug, details(SEO), header_image, thumbnail(OG), status, position_order; Policy + validation.
- [ ] 1.2 Image pipeline — upload → optimize to responsive **WebP**, alt text, store via StorageRepository.
- [ ] 1.3 Product CRUD — main image + max 6 images, video(yt), price/discount, advance-payment rules, is_featured/is_new, position_order, SEO, social thumbnail (falls back to main image).
- [ ] 1.4 Stock logic — "in stock" only if `stock_status && stock_amount>0`.
- [ ] 1.5 Product list (admin) — server-side table: search (keyword/SKU/slug), filters (status/category/stock/date range), sort, pagination.
- [ ] 1.6 Soft delete + **recycle bin** (restore / hard-delete) + CSV export.
- [ ] 1.7 Storefront read API — categories list, category+products (paginated), single product.

## PHASE 2 — Storefront read pages (Next 16)  `feat/phase-2-storefront`
**Features:** home, category, product UI; floating buttons; mobile-first.
- [ ] 2.1 Layout + theme (shadcn, Tailwind 4, dark UI like Lovinna), SEO/OG base.
- [ ] 2.2 Home — full-width banner, hero CTAs, Featured Collections (2-col desktop / 1-col mobile), info section, footer (contact/email/whatsapp/address).
- [ ] 2.3 Floating **WhatsApp** button (bottom-right) + floating **menu** (bottom-left) → left drawer category list.
- [ ] 2.4 Category page — 80vh header image, name+detail, **infinite scroll** product list (1/row), categories grid at the end. ISR + suspense + skeletons.
- [ ] 2.5 Product block — image slider (arrows + thumbnails); 3 buttons: **Price** (struck original + discount), **Inquiry** (WhatsApp deep link w/ image+title+SKU+details), **Order Now** (modal).
- [ ] 2.6 Order modal — image, sku, qty +/-, "Order on WhatsApp" / "Order on Web".

## PHASE 3 — Orders & web checkout  `feat/phase-3-orders`
**Features:** checkout, order placement, admin orders, invoice.
- [ ] 3.1 Shipping zones CRUD (admin) + API.
- [ ] 3.2 Web checkout page — qty +/-, name, mobile (readonly `+88`, 11-digit BD validation), address, zone select, live subtotal+shipping=total, optional email; advance-payment notices per product rule.
- [ ] 3.3 `PlaceOrder` action — persist order + items (price snapshot) + **customer IP/UA**, auto-register customer by mobile, order_no, transaction-safe.
- [ ] 3.4 Success/celebration page — order details + **invoice PDF** download + optional pay buttons (full / shipping).
- [ ] 3.5 Admin orders — list (filter/sort/search by status/mobile/name/address/date), view page, status change, invoice PDF.

## PHASE 4 — Payments / Auth / SMS / Courier  `feat/phase-4-integrations`
**Features:** SSLCommerz, OTP login, SMS, SteadFast, SMTP.
- [ ] 4.1 Customer **OTP auth** — request/verify (hashed OTP, rate-limited), Sanctum tokens, register/login UI.
- [ ] 4.2 **SSLCommerz** (dynamic creds) — init, IPN/validation, **server-side `val_id` verify**, idempotent payment record, success/fail/cancel pages + receipt.
- [ ] 4.3 **SMS** — provider-agnostic `SmsGateway` interface + order-confirmation SMS (concrete BD adapter later).
- [ ] 4.4 **SteadFast** courier (dynamic) — create consignment, track status.
- [ ] 4.5 **SMTP** dynamic settings + test-send.

## PHASE 5 — Marketing / SEO / Analytics  `feat/phase-5-marketing`
**Features:** dynamic tags, server-side tracking, SEO, feeds.
- [ ] 5.1 Dynamic **GTM / GA4 / Meta Pixel / MS Clarity** injection into Next (public IDs only).
- [ ] 5.2 Server-side **Meta CAPI / Conversion API** (token server-side), event dedup, click/view/purchase events.
- [ ] 5.3 **Visitor tracking** + IP capture.
- [ ] 5.4 Dynamic **SEO** — meta/OG/canonical, Product + Breadcrumb JSON-LD, sitemap.xml, robots.txt, dynamic OG thumbnails (home → header banner fallback).
- [ ] 5.5 **Meta commerce / Google Merchant** — scheduled CSV/feed export + product feed endpoint.

## PHASE 6 — Settings surfaces & hardening  `feat/phase-6-hardening`
- [ ] 6.1 Admin editors — contact, home banner, footer, WhatsApp number, all gateway settings.
- [ ] 6.2 Owner **Maintenance Lock** + session revocation + key rotation (reversible, audited).
- [ ] 6.3 Security pass — rate limits, CSP/HSTS headers, CORS locked to storefront origin, `composer/npm audit`, Larastan max, perf/load check, backup strategy.

## PHASE 7 — License module (FUTURE, only if needed)
- [ ] 7.1 Signed heartbeat + reversible **Suspended** state + grace period (disable, never destroy). See MASTER-PLAN §9.

---
### Milestone tags
`v0.1.0` after Phase 1 · `v0.2.0` after Phase 3 · `v0.3.0` after Phase 5 · `v1.0.0` after Phase 6.
